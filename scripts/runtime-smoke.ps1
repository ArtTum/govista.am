[CmdletBinding()]
param(
    [string]$ApiBase = 'http://127.0.0.1:8010',
    [string]$AdminEmail = 'admin@govista.am',
    [string]$AdminPassword = 'GoVista2026!',
    [string]$UploadFile = ''
)

$ErrorActionPreference = 'Stop'
$results = [System.Collections.Generic.List[object]]::new()
$createdRecords = [System.Collections.Generic.List[object]]::new()
$adminHeaders = @{}

function Add-Result {
    param(
        [string]$Name,
        [int]$Status,
        [int]$Expected
    )

    $passed = $Status -eq $Expected
    $results.Add([pscustomobject]@{
        Name = $Name
        Status = $Status
        Expected = $Expected
        Pass = $passed
    })

    $label = if ($passed) { 'PASS' } else { 'FAIL' }
    Write-Host ('[{0}] {1} ({2})' -f $label, $Name, $Status)
}

function Invoke-Api {
    param(
        [string]$Name,
        [ValidateSet('GET', 'POST', 'PUT', 'DELETE')]
        [string]$Method,
        [string]$Url,
        [int]$Expected = 200,
        [object]$Body = $null,
        [hashtable]$Headers = @{}
    )

    $parameters = @{
        Uri = $Url
        Method = $Method
        Headers = $Headers
        UseBasicParsing = $true
        TimeoutSec = 15
    }

    if ($null -ne $Body) {
        $parameters.ContentType = 'application/json'
        $parameters.Body = $Body | ConvertTo-Json -Depth 15 -Compress
    }

    $status = 0
    $content = ''

    try {
        $response = Invoke-WebRequest @parameters
        $status = [int]$response.StatusCode
        $content = $response.Content
    }
    catch {
        if ($null -ne $_.Exception.Response) {
            $status = [int]$_.Exception.Response.StatusCode
        }
    }

    Add-Result -Name $Name -Status $status -Expected $Expected

    if ($content) {
        try {
            return $content | ConvertFrom-Json
        }
        catch {
            return $content
        }
    }

    return $null
}

function New-Translations {
    param([string]$Text)

    return @{
        hy = "$Text HY"
        ru = "$Text RU"
        en = "$Text EN"
    }
}

$locales = @('hy', 'ru', 'en')
$publicResources = @{
    tours = @(
        'garni-geghard-symphony',
        'khor-virap-noravank-areni',
        'sevan-dilijan-forest',
        'tatev-wings-private',
        'armenia-seven-day-signature',
        'gyumri-cultural-day',
        'lori-monasteries-canyon',
        'jermuk-waterfall-wellness',
        'aragats-amberd-adventure',
        'echmiadzin-zvartnots',
        'khndzoresk-cave-city',
        'lake-arpi-north',
        'dubai-city-desert',
        'istanbul-bosphorus-weekend',
        'tbilisi-kakheti-wine',
        'paris-romantic-escape',
        'rome-eternal-city',
        'athens-santorini',
        'cairo-hurghada',
        'maldives-island-escape',
        'prague-vienna-budapest',
        'barcelona-costa-brava',
        'cyprus-sun-sea',
        'montenegro-adriatic'
    )
    destinations = @('yerevan', 'lake-sevan', 'dilijan', 'tatev', 'areni', 'garni')
    posts = @('first-time-armenia-guide', 'best-season-armenia', 'armenian-flavors')
    pages = @('about', 'privacy', 'terms')
}

foreach ($locale in $locales) {
    Invoke-Api "home [$locale]" GET "$ApiBase/api/v1/home?locale=$locale" | Out-Null
    Invoke-Api "tours [$locale]" GET "$ApiBase/api/v1/tours?locale=$locale" | Out-Null
    Invoke-Api "destinations [$locale]" GET "$ApiBase/api/v1/destinations?locale=$locale" | Out-Null
    Invoke-Api "services [$locale]" GET "$ApiBase/api/v1/services?locale=$locale" | Out-Null
    Invoke-Api "posts [$locale]" GET "$ApiBase/api/v1/posts?locale=$locale" | Out-Null

    foreach ($resource in $publicResources.Keys) {
        foreach ($slug in $publicResources[$resource]) {
            Invoke-Api "$resource/$slug [$locale]" GET "$ApiBase/api/v1/$resource/${slug}?locale=${locale}" | Out-Null
        }
    }
}

foreach ($type in @('group', 'private', 'package')) {
    Invoke-Api "tour filter [$type]" GET "$ApiBase/api/v1/tours?type=$type&locale=en" | Out-Null
}

foreach ($scope in @('domestic', 'international')) {
    Invoke-Api "tour scope [$scope]" GET "$ApiBase/api/v1/tours?travel_scope=$scope&locale=en&per_page=48" | Out-Null
}

foreach ($type in @('accommodation', 'transport', 'events', 'custom')) {
    Invoke-Api "service filter [$type]" GET "$ApiBase/api/v1/services?type=$type&locale=en" | Out-Null
}

Invoke-Api 'negative pagination is clamped' GET "$ApiBase/api/v1/tours?per_page=-1" | Out-Null
Invoke-Api 'large pagination is clamped' GET "$ApiBase/api/v1/tours?per_page=999" | Out-Null
Invoke-Api 'invalid locale falls back safely' GET "$ApiBase/api/v1/home?locale=invalid" | Out-Null
Invoke-Api 'missing tour' GET "$ApiBase/api/v1/tours/does-not-exist" 404 | Out-Null
Invoke-Api 'missing destination' GET "$ApiBase/api/v1/destinations/does-not-exist" 404 | Out-Null
Invoke-Api 'missing post' GET "$ApiBase/api/v1/posts/does-not-exist" 404 | Out-Null
Invoke-Api 'missing page' GET "$ApiBase/api/v1/pages/does-not-exist" 404 | Out-Null

Invoke-Api 'booking validation' POST "$ApiBase/api/v1/bookings" 422 @{} @{ Accept = 'application/json' } | Out-Null
Invoke-Api 'contact validation' POST "$ApiBase/api/v1/contact" 422 @{} @{ Accept = 'application/json' } | Out-Null
Invoke-Api 'admin dashboard requires authentication' GET "$ApiBase/api/admin/dashboard" 401 $null @{ Accept = 'application/json' } | Out-Null
Invoke-Api 'admin invalid login' POST "$ApiBase/api/admin/login" 422 @{
    email = $AdminEmail
    password = 'invalid-password'
} @{ Accept = 'application/json' } | Out-Null

$login = Invoke-Api 'admin login' POST "$ApiBase/api/admin/login" 200 @{
    email = $AdminEmail
    password = $AdminPassword
} @{ Accept = 'application/json' }

if ($null -ne $login -and $login.token) {
    $adminHeaders = @{
        Accept = 'application/json'
        Authorization = "Bearer $($login.token)"
    }

    Invoke-Api 'admin current user' GET "$ApiBase/api/admin/me" 200 $null $adminHeaders | Out-Null
    Invoke-Api 'admin dashboard' GET "$ApiBase/api/admin/dashboard" 200 $null $adminHeaders | Out-Null

    foreach ($resource in @(
        'tours',
        'destinations',
        'services',
        'posts',
        'pages',
        'testimonials',
        'faqs',
        'settings',
        'bookings',
        'contact-messages'
    )) {
        Invoke-Api "admin index [$resource]" GET "$ApiBase/api/admin/content/${resource}?per_page=-1" 200 $null $adminHeaders | Out-Null
    }

    Invoke-Api 'admin unknown resource' GET "$ApiBase/api/admin/content/unknown" 404 $null $adminHeaders | Out-Null

    $stamp = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()
    $resourcePayloads = [ordered]@{
        tours = @{
            slug = "runtime-qa-tour-$stamp"
            travel_scope = 'domestic'
            type = 'private'
            title = New-Translations 'Runtime QA tour'
            description = New-Translations 'Runtime QA description'
            price = 10000
            currency = 'AMD'
            active = $true
            featured = $false
        }
        destinations = @{
            slug = "runtime-qa-destination-$stamp"
            title = New-Translations 'Runtime QA destination'
            description = New-Translations 'Runtime QA description'
            active = $true
        }
        services = @{
            slug = "runtime-qa-service-$stamp"
            type = 'custom'
            title = New-Translations 'Runtime QA service'
            description = New-Translations 'Runtime QA description'
            currency = 'AMD'
            active = $true
        }
        posts = @{
            slug = "runtime-qa-post-$stamp"
            category = 'qa'
            title = New-Translations 'Runtime QA post'
            excerpt = New-Translations 'Runtime QA excerpt'
            content = New-Translations 'Runtime QA content'
            reading_time = 3
            published_at = '2026-07-27 12:00:00'
            active = $true
        }
        pages = @{
            slug = "runtime-qa-page-$stamp"
            title = New-Translations 'Runtime QA page'
            content = New-Translations 'Runtime QA content'
            active = $true
        }
        testimonials = @{
            name = "Runtime QA Traveler $stamp"
            country = New-Translations 'Runtime QA country'
            message = New-Translations 'Runtime QA message'
            rating = 5
            active = $true
        }
        faqs = @{
            question = New-Translations 'Runtime QA question'
            answer = New-Translations 'Runtime QA answer'
            category = 'qa'
            active = $true
        }
        settings = @{
            group = 'qa'
            key = "runtime_qa_setting_$stamp"
            value = New-Translations 'Runtime QA value'
            type = 'text'
        }
    }

    foreach ($entry in $resourcePayloads.GetEnumerator()) {
        $resource = $entry.Key
        $payload = $entry.Value
        $created = Invoke-Api "admin create [$resource]" POST "$ApiBase/api/admin/content/$resource" 201 $payload $adminHeaders

        if ($null -ne $created -and $created.id) {
            $createdRecords.Add([pscustomobject]@{ Resource = $resource; Id = $created.id })
            Invoke-Api "admin show [$resource]" GET "$ApiBase/api/admin/content/$resource/$($created.id)" 200 $null $adminHeaders | Out-Null

            if ($payload.ContainsKey('title')) {
                $payload.title = New-Translations 'Runtime QA updated'
            }
            elseif ($resource -eq 'testimonials') {
                $payload.name = "Runtime QA Traveler Updated $stamp"
            }
            elseif ($resource -eq 'faqs') {
                $payload.question = New-Translations 'Runtime QA updated'
            }
            else {
                $payload.value = New-Translations 'Runtime QA updated'
            }

            Invoke-Api "admin update [$resource]" PUT "$ApiBase/api/admin/content/$resource/$($created.id)" 200 $payload $adminHeaders | Out-Null
        }
    }

    $booking = Invoke-Api 'create booking' POST "$ApiBase/api/v1/bookings" 201 @{
        type = 'tour'
        item_title = 'Runtime QA tour'
        name = 'Runtime QA Guest'
        email = "runtime-booking-$stamp@govista.test"
        participants = 2
        locale = 'en'
    } @{ Accept = 'application/json' }

    if ($null -ne $booking -and $booking.reference) {
        $bookingList = Invoke-Api 'find booking in admin' GET "$ApiBase/api/admin/content/bookings?search=$($booking.reference)" 200 $null $adminHeaders
        $bookingRecord = $bookingList.data | Select-Object -First 1

        if ($null -ne $bookingRecord) {
            $createdRecords.Add([pscustomobject]@{ Resource = 'bookings'; Id = $bookingRecord.id })
            Invoke-Api 'show booking in admin' GET "$ApiBase/api/admin/content/bookings/$($bookingRecord.id)" 200 $null $adminHeaders | Out-Null
            Invoke-Api 'update booking in admin' PUT "$ApiBase/api/admin/content/bookings/$($bookingRecord.id)" 200 @{
                status = 'confirmed'
                message = 'Runtime QA confirmed'
                total_price = 12345
            } $adminHeaders | Out-Null
        }
    }

    Invoke-Api 'create contact message' POST "$ApiBase/api/v1/contact" 201 @{
        name = 'Runtime QA Contact'
        email = "runtime-contact-$stamp@govista.test"
        subject = 'Runtime QA'
        message = 'Runtime QA contact message'
        locale = 'en'
    } @{ Accept = 'application/json' } | Out-Null

    $contactList = Invoke-Api 'find contact in admin' GET "$ApiBase/api/admin/content/contact-messages?search=runtime-contact-$stamp" 200 $null $adminHeaders
    $contactRecord = $contactList.data | Select-Object -First 1

    if ($null -ne $contactRecord -and $contactRecord.email -eq "runtime-contact-$stamp@govista.test") {
        $createdRecords.Add([pscustomobject]@{ Resource = 'contact-messages'; Id = $contactRecord.id })
        Invoke-Api 'show contact in admin' GET "$ApiBase/api/admin/content/contact-messages/$($contactRecord.id)" 200 $null $adminHeaders | Out-Null
        Invoke-Api 'update contact in admin' PUT "$ApiBase/api/admin/content/contact-messages/$($contactRecord.id)" 200 @{
            status = 'replied'
        } $adminHeaders | Out-Null
    }

    if (-not $UploadFile) {
        $UploadFile = Join-Path $PSScriptRoot '..\frontend\public\brand\govista-logo.png'
    }

    $uploadJson = & curl.exe -sS -X POST `
        -H "Authorization: Bearer $($login.token)" `
        -H 'Accept: application/json' `
        -F "file=@$UploadFile;type=image/png" `
        "$ApiBase/api/admin/upload"

    $uploadText = $uploadJson -join [Environment]::NewLine
    try {
        $upload = $uploadText | ConvertFrom-Json
    }
    catch {
        $upload = $null
    }

    Add-Result 'admin image upload' $(if ($upload.path) { 201 } else { 0 }) 201

    if ($upload.path) {
        $storageRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\backend\storage\app\public'))
        $uploadedPath = [System.IO.Path]::GetFullPath((Join-Path $storageRoot $upload.path))

        if ($uploadedPath.StartsWith($storageRoot, [System.StringComparison]::OrdinalIgnoreCase) -and (Test-Path -LiteralPath $uploadedPath)) {
            Remove-Item -LiteralPath $uploadedPath -Force
        }
    }

    foreach ($record in $createdRecords) {
        Invoke-Api "admin delete [$($record.Resource)]" DELETE "$ApiBase/api/admin/content/$($record.Resource)/$($record.Id)" 200 $null $adminHeaders | Out-Null
        Invoke-Api "admin confirms deletion [$($record.Resource)]" GET "$ApiBase/api/admin/content/$($record.Resource)/$($record.Id)" 404 $null $adminHeaders | Out-Null
    }

    Invoke-Api 'admin logout' POST "$ApiBase/api/admin/logout" 200 $null $adminHeaders | Out-Null
    Invoke-Api 'logged-out token is rejected' GET "$ApiBase/api/admin/me" 401 $null $adminHeaders | Out-Null
}

$failures = @($results | Where-Object { -not $_.Pass })

Write-Host ''
Write-Host ('Requests: {0}; passed: {1}; failed: {2}' -f $results.Count, ($results.Count - $failures.Count), $failures.Count)

if ($failures.Count -gt 0) {
    $failures | Format-Table -AutoSize
    exit 1
}

exit 0
