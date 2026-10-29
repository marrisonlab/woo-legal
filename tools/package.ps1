param()

$ErrorActionPreference = 'Stop'
$repositoryPath = Split-Path -Parent $PSScriptRoot
$pluginPath = Join-Path $repositoryPath 'woo-legal'
$mainFile = Join-Path $pluginPath 'woo-legal-returns.php'
$mainSource = [System.IO.File]::ReadAllText($mainFile)
$versionMatch = [regex]::Match($mainSource, "define\( 'WLR_VERSION', '([^']+)' \)")
if (-not $versionMatch.Success) { throw 'Versione del plugin non trovata.' }
$packageDirectory = Join-Path $repositoryPath 'dist'
New-Item -ItemType Directory -Path $packageDirectory -Force | Out-Null
$packagePath = Join-Path $packageDirectory ('woo-legal-' + $versionMatch.Groups[1].Value + '.zip')
$temporaryPath = $packagePath + '.' + [guid]::NewGuid().ToString('N') + '.tmp'

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$stream = [System.IO.File]::Open($temporaryPath, [System.IO.FileMode]::CreateNew)
$archive = [System.IO.Compression.ZipArchive]::new($stream, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($pluginFile in (Get-ChildItem -LiteralPath $pluginPath -Recurse -File | Sort-Object FullName)) {
        $relativePath = $pluginFile.FullName.Substring($pluginPath.Length + 1).Replace('\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $pluginFile.FullName, ('woo-legal/' + $relativePath), [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
} finally {
    $archive.Dispose()
    $stream.Dispose()
}
Move-Item -LiteralPath $temporaryPath -Destination $packagePath -Force
Write-Output $packagePath
