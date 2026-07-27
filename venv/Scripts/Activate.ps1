<#
.Synopsis
Activate a Python virtual environment for the current PowerShell session.

.Description
Pushes the python executable for a virtual environment to the front of the
$Env:PATH environment variable and sets the prompt to signify that you are
in a Python virtual environment. Makes use of the command line switches as
well as the `pyvenv.cfg` file values present in the virtual environment.

.Parameter VenvDir
Path to the directory that contains the virtual environment to activate. The
default value for this is the parent of the directory that the Activate.ps1
script is located within.

.Parameter Prompt
The prompt prefix to display when this virtual environment is activated. By
default, this prompt is the name of the virtual environment folder (VenvDir)
surrounded by parentheses and followed by a single space (ie. '(.venv) ').

.Example
Activate.ps1
Activates the Python virtual environment that contains the Activate.ps1 script.

.Example
Activate.ps1 -Verbose
Activates the Python virtual environment that contains the Activate.ps1 script,
and shows extra information about the activation as it executes.

.Example
Activate.ps1 -VenvDir C:\Users\MyUser\Common\.venv
Activates the Python virtual environment located in the specified location.

.Example
Activate.ps1 -Prompt "MyPython"
Activates the Python virtual environment that contains the Activate.ps1 script,
and prefixes the current prompt with the specified string (surrounded in
parentheses) while the virtual environment is active.

.Notes
On Windows, it may be required to enable this Activate.ps1 script by setting the
execution policy for the user. You can do this by issuing the following PowerShell
command:

PS C:\> Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser

For more information on Execution Policies: 
https://go.microsoft.com/fwlink/?LinkID=135170

#>
Param(
    [Parameter(Mandatory = $false)]
    [String]
    $VenvDir,
    [Parameter(Mandatory = $false)]
    [String]
    $Prompt
)

<# Function declarations --------------------------------------------------- #>

<#
.Synopsis
Remove all shell session elements added by the Activate script, including the
addition of the virtual environment's Python executable from the beginning of
the PATH variable.

.Parameter NonDestructive
If present, do not remove this function from the global namespace for the
session.

#>
function global:deactivate ([switch]$NonDestructive) {
    # Revert to original values

    # The prior prompt:
    if (Test-Path -Path Function:_OLD_VIRTUAL_PROMPT) {
        Copy-Item -Path Function:_OLD_VIRTUAL_PROMPT -Destination Function:prompt
        Remove-Item -Path Function:_OLD_VIRTUAL_PROMPT
    }

    # The prior PYTHONHOME:
    if (Test-Path -Path Env:_OLD_VIRTUAL_PYTHONHOME) {
        Copy-Item -Path Env:_OLD_VIRTUAL_PYTHONHOME -Destination Env:PYTHONHOME
        Remove-Item -Path Env:_OLD_VIRTUAL_PYTHONHOME
    }

    # The prior PATH:
    if (Test-Path -Path Env:_OLD_VIRTUAL_PATH) {
        Copy-Item -Path Env:_OLD_VIRTUAL_PATH -Destination Env:PATH
        Remove-Item -Path Env:_OLD_VIRTUAL_PATH
    }

    # Just remove the VIRTUAL_ENV altogether:
    if (Test-Path -Path Env:VIRTUAL_ENV) {
        Remove-Item -Path env:VIRTUAL_ENV
    }

    # Just remove VIRTUAL_ENV_PROMPT altogether.
    if (Test-Path -Path Env:VIRTUAL_ENV_PROMPT) {
        Remove-Item -Path env:VIRTUAL_ENV_PROMPT
    }

    # Just remove the _PYTHON_VENV_PROMPT_PREFIX altogether:
    if (Get-Variable -Name "_PYTHON_VENV_PROMPT_PREFIX" -ErrorAction SilentlyContinue) {
        Remove-Variable -Name _PYTHON_VENV_PROMPT_PREFIX -Scope Global -Force
    }

    # Leave deactivate function in the global namespace if requested:
    if (-not $NonDestructive) {
        Remove-Item -Path function:deactivate
    }
}

<#
.Description
Get-PyVenvConfig parses the values from the pyvenv.cfg file located in the
given folder, and returns them in a map.

For each line in the pyvenv.cfg file, if that line can be parsed into exactly
two strings separated by `=` (with any amount of whitespace surrounding the =)
then it is considered a `key = value` line. The left hand string is the key,
the right hand is the value.

If the value starts with a `'` or a `"` then the first and last character is
stripped from the value before being captured.

.Parameter ConfigDir
Path to the directory that contains the `pyvenv.cfg` file.
#>
function Get-PyVenvConfig(
    [String]
    $ConfigDir
) {
    Write-Verbose "Given ConfigDir=$ConfigDir, obtain values in pyvenv.cfg"

    # Ensure the file exists, and issue a warning if it doesn't (but still allow the function to continue).
    $pyvenvConfigPath = Join-Path -Resolve -Path $ConfigDir -ChildPath 'pyvenv.cfg' -ErrorAction Continue

    # An empty map will be returned if no config file is found.
    $pyvenvConfig = @{ }

    if ($pyvenvConfigPath) {

        Write-Verbose "File exists, parse `key = value` lines"
        $pyvenvConfigContent = Get-Content -Path $pyvenvConfigPath

        $pyvenvConfigContent | ForEach-Object {
            $keyval = $PSItem -split "\s*=\s*", 2
            if ($keyval[0] -and $keyval[1]) {
                $val = $keyval[1]

                # Remove extraneous quotations around a string value.
                if ("'""".Contains($val.Substring(0, 1))) {
                    $val = $val.Substring(1, $val.Length - 2)
                }

                $pyvenvConfig[$keyval[0]] = $val
                Write-Verbose "Adding Key: '$($keyval[0])'='$val'"
            }
        }
    }
    return $pyvenvConfig
}


<# Begin Activate script --------------------------------------------------- #>

# Determine the containing directory of this script
$VenvExecPath = Split-Path -Parent $MyInvocation.MyCommand.Definition
$VenvExecDir = Get-Item -Path $VenvExecPath

Write-Verbose "Activation script is located in path: '$VenvExecPath'"
Write-Verbose "VenvExecDir Fullname: '$($VenvExecDir.FullName)"
Write-Verbose "VenvExecDir Name: '$($VenvExecDir.Name)"

# Set values required in priority: CmdLine, ConfigFile, Default
# First, get the location of the virtual environment, it might not be
# VenvExecDir if specified on the command line.
if ($VenvDir) {
    Write-Verbose "VenvDir given as parameter, using '$VenvDir' to determine values"
}
else {
    Write-Verbose "VenvDir not given as a parameter, using parent directory name as VenvDir."
    $VenvDir = $VenvExecDir.Parent.FullName.TrimEnd("\\/")
    Write-Verbose "VenvDir=$VenvDir"
}

# Next, read the `pyvenv.cfg` file to determine any required value such
# as `prompt`.
$pyvenvCfg = Get-PyVenvConfig -ConfigDir $VenvDir

# Next, set the prompt from the command line, or the config file, or
# just use the name of the virtual environment folder.
if ($Prompt) {
    Write-Verbose "Prompt specified as argument, using '$Prompt'"
}
else {
    Write-Verbose "Prompt not specified as argument to script, checking pyvenv.cfg value"
    if ($pyvenvCfg -and $pyvenvCfg['prompt']) {
        Write-Verbose "  Setting based on value in pyvenv.cfg='$($pyvenvCfg['prompt'])'"
        $Prompt = $pyvenvCfg['prompt'];
    }
    else {
        Write-Verbose "  Setting prompt based on parent's directory's name. (Is the directory name passed to venv module when creating the virtual environment)"
        Write-Verbose "  Got leaf-name of $VenvDir='$(Split-Path -Path $venvDir -Leaf)'"
        $Prompt = Split-Path -Path $venvDir -Leaf
    }
}

Write-Verbose "Prompt = '$Prompt'"
Write-Verbose "VenvDir='$VenvDir'"

# Deactivate any currently active virtual environment, but leave the
# deactivate function in place.
deactivate -nondestructive

# Now set the environment variable VIRTUAL_ENV, used by many tools to determine
# that there is an activated venv.
$env:VIRTUAL_ENV = $VenvDir

$env:VIRTUAL_ENV_PROMPT = $Prompt

if (-not $Env:VIRTUAL_ENV_DISABLE_PROMPT) {

    Write-Verbose "Setting prompt to '$Prompt'"

    # Set the prompt to include the env name
    # Make sure _OLD_VIRTUAL_PROMPT is global
    function global:_OLD_VIRTUAL_PROMPT { "" }
    Copy-Item -Path function:prompt -Destination function:_OLD_VIRTUAL_PROMPT
    New-Variable -Name _PYTHON_VENV_PROMPT_PREFIX -Description "Python virtual environment prompt prefix" -Scope Global -Option ReadOnly -Visibility Public -Value $Prompt

    function global:prompt {
        Write-Host -NoNewline -ForegroundColor Green "($_PYTHON_VENV_PROMPT_PREFIX) "
        _OLD_VIRTUAL_PROMPT
    }
}

# Clear PYTHONHOME
if (Test-Path -Path Env:PYTHONHOME) {
    Copy-Item -Path Env:PYTHONHOME -Destination Env:_OLD_VIRTUAL_PYTHONHOME
    Remove-Item -Path Env:PYTHONHOME
}

# Add the venv to the PATH
Copy-Item -Path Env:PATH -Destination Env:_OLD_VIRTUAL_PATH
$Env:PATH = "$VenvExecDir$([System.IO.Path]::PathSeparator)$Env:PATH"

# SIG # Begin signature block
# MII27wYJKoZIhvcNAQcCoII24DCCNtwCAQExDzANBglghkgBZQMEAgEFADB5Bgor
# BgEEAYI3AgEEoGswaTA0BgorBgEEAYI3AgEeMCYCAwEAAAQQH8w7YFlLCE63JNLG
# KX7zUQIBAAIBAAIBAAIBAAIBADAxMA0GCWCGSAFlAwQCAQUABCBALKwKRFIhr2RY
# IW/WJLd9pc8a9sj/IoThKU92fTfKsKCCG1wwggXMMIIDtKADAgECAhBUmNLR1FsZ
# lUgTecgRwIeZMA0GCSqGSIb3DQEBDAUAMHcxCzAJBgNVBAYTAlVTMR4wHAYDVQQK
# ExVNaWNyb3NvZnQgQ29ycG9yYXRpb24xSDBGBgNVBAMTP01pY3Jvc29mdCBJZGVu
# dGl0eSBWZXJpZmljYXRpb24gUm9vdCBDZXJ0aWZpY2F0ZSBBdXRob3JpdHkgMjAy
# MDAeFw0yMDA0MTYxODM2MTZaFw00NTA0MTYxODQ0NDBaMHcxCzAJBgNVBAYTAlVT
# MR4wHAYDVQQKExVNaWNyb3NvZnQgQ29ycG9yYXRpb24xSDBGBgNVBAMTP01pY3Jv
# c29mdCBJZGVudGl0eSBWZXJpZmljYXRpb24gUm9vdCBDZXJ0aWZpY2F0ZSBBdXRo
# b3JpdHkgMjAyMDCCAiIwDQYJKoZIhvcNAQEBBQADggIPADCCAgoCggIBALORKgeD
# Bmf9np3gx8C3pOZCBH8Ppttf+9Va10Wg+3cL8IDzpm1aTXlT2KCGhFdFIMeiVPvH
# or+Kx24186IVxC9O40qFlkkN/76Z2BT2vCcH7kKbK/ULkgbk/WkTZaiRcvKYhOuD
# PQ7k13ESSCHLDe32R0m3m/nJxxe2hE//uKya13NnSYXjhr03QNAlhtTetcJtYmrV
# qXi8LW9J+eVsFBT9FMfTZRY33stuvF4pjf1imxUs1gXmuYkyM6Nix9fWUmcIxC70
# ViueC4fM7Ke0pqrrBc0ZV6U6CwQnHJFnni1iLS8evtrAIMsEGcoz+4m+mOJyoHI1
# vnnhnINv5G0Xb5DzPQCGdTiO0OBJmrvb0/gwytVXiGhNctO/bX9x2P29Da6SZEi3
# W295JrXNm5UhhNHvDzI9e1eM80UHTHzgXhgONXaLbZ7LNnSrBfjgc10yVpRnlyUK
# xjU9lJfnwUSLgP3B+PR0GeUw9gb7IVc+BhyLaxWGJ0l7gpPKWeh1R+g/OPTHU3mg
# trTiXFHvvV84wRPmeAyVWi7FQFkozA8kwOy6CXcjmTimthzax7ogttc32H83rwjj
# O3HbbnMbfZlysOSGM1l0tRYAe1BtxoYT2v3EOYI9JACaYNq6lMAFUSw0rFCZE4e7
# swWAsk0wAly4JoNdtGNz764jlU9gKL431VulAgMBAAGjVDBSMA4GA1UdDwEB/wQE
# AwIBhjAPBgNVHRMBAf8EBTADAQH/MB0GA1UdDgQWBBTIftJqhSobyhmYBAcnz1AQ
# T2ioojAQBgkrBgEEAYI3FQEEAwIBADANBgkqhkiG9w0BAQwFAAOCAgEAr2rd5hnn
# LZRDGU7L6VCVZKUDkQKL4jaAOxWiUsIWGbZqWl10QzD0m/9gdAmxIR6QFm3FJI9c
# Zohj9E/MffISTEAQiwGf2qnIrvKVG8+dBetJPnSgaFvlVixlHIJ+U9pW2UYXeZJF
# xBA2CFIpF8svpvJ+1Gkkih6PsHMNzBxKq7Kq7aeRYwFkIqgyuH4yKLNncy2RtNwx
# AQv3Rwqm8ddK7VZgxCwIo3tAsLx0J1KH1r6I3TeKiW5niB31yV2g/rarOoDXGpc8
# FzYiQR6sTdWD5jw4vU8w6VSp07YEwzJ2YbuwGMUrGLPAgNW3lbBeUU0i/OxYqujY
# lLSlLu2S3ucYfCFX3VVj979tzR/SpncocMfiWzpbCNJbTsgAlrPhgzavhgplXHT2
# 6ux6anSg8Evu75SjrFDyh+3XOjCDyft9V77l4/hByuVkrrOj7FjshZrM77nq81YY
# uVxzmq/FdxeDWds3GhhyVKVB0rYjdaNDmuV3fJZ5t0GNv+zcgKCf0Xd1WF81E+Al
# GmcLfc4l+gcK5GEh2NQc5QfGNpn0ltDGFf5Ozdeui53bFv0ExpK91IjmqaOqu/dk
# ODtfzAzQNb50GQOmxapMomE2gj4d8yu8l13bS3g7LfU772Aj6PXsCyM2la+YZr9T
# 03u4aUoqlmZpxJTG9F9urJh4iIAGXKKy7aIwgga6MIIEoqADAgECAhMzAAJeplh/
# RjTVxNVWAAAAAl6mMA0GCSqGSIb3DQEBDAUAMFoxCzAJBgNVBAYTAlVTMR4wHAYD
# VQQKExVNaWNyb3NvZnQgQ29ycG9yYXRpb24xKzApBgNVBAMTIk1pY3Jvc29mdCBJ
# RCBWZXJpZmllZCBDUyBBT0MgQ0EgMDMwHhcNMjYwNjIyMDg1MTQwWhcNMjYwNjI1
# MDg1MTQwWjB8MQswCQYDVQQGEwJVUzEPMA0GA1UECBMGT3JlZ29uMRIwEAYDVQQH
# EwlCZWF2ZXJ0b24xIzAhBgNVBAoTGlB5dGhvbiBTb2Z0d2FyZSBGb3VuZGF0aW9u
# MSMwIQYDVQQDExpQeXRob24gU29mdHdhcmUgRm91bmRhdGlvbjCCAaIwDQYJKoZI
# hvcNAQEBBQADggGPADCCAYoCggGBAIkAGjq+xesUCil0ymVOgxkwwpyiA8LTBpPp
# sgZZFRhZ3a34w7zJ/wL/7FP2Flj8hZy0YhXIWBy0V903651unowhmVQ4XKp2TsmE
# XOL+cdGremHkAg1F0vltNLSg+gmWRbUBqljujBJxutghHKeTU3tj9/GXC6gXYOgq
# Yd3RqNiZpcjn60xjoHcBZ3GtO6QD13OiDOsIwoh23bkh3V73tXpqoV5Wii+nH07h
# en+zWz/bM4WWTxTlV+IYO7iXKJFNv6x04rl2DZ4dWAh74PwOzGVAHAqCvbBPWB+U
# alEt5ImEuFLkrUXm1BN6nXrvTY8LZAvMLnOjLpZyzIdavPNLHHcPdLm4I95EQY8s
# l/g25533tvxp67IQOnmKkIZ5tV42nFyyuQxql2sfjLqPG4/BvOxKmnibDjpIDpE8
# lCblb9PFYgos3ZqBHoYMBVfwOKFXP9/WX1qyUh3+B9whS8s5wlNfiUuS9tO/mJlQ
# xMWN4OzMPfwBgibvwEC/uaCX0WAZJwIDAQABo4IB1TCCAdEwDAYDVR0TAQH/BAIw
# ADAOBgNVHQ8BAf8EBAMCB4AwPAYDVR0lBDUwMwYKKwYBBAGCN2EBAAYIKwYBBQUH
# AwMGGysGAQQBgjdhgqKNuwqmkohkgZH0oEWCk/3hbzAdBgNVHQ4EFgQUWHBT6qDk
# 8cCbxsJhoCproWH+xaIwHwYDVR0jBBgwFoAUpEMMf3ZapYXnPo0oDwwXokVpcMYw
# ZwYDVR0fBGAwXjBcoFqgWIZWaHR0cDovL3d3dy5taWNyb3NvZnQuY29tL3BraW9w
# cy9jcmwvTWljcm9zb2Z0JTIwSUQlMjBWZXJpZmllZCUyMENTJTIwQU9DJTIwQ0El
# MjAwMy5jcmwwdAYIKwYBBQUHAQEEaDBmMGQGCCsGAQUFBzAChlhodHRwOi8vd3d3
# Lm1pY3Jvc29mdC5jb20vcGtpb3BzL2NlcnRzL01pY3Jvc29mdCUyMElEJTIwVmVy
# aWZpZWQlMjBDUyUyMEFPQyUyMENBJTIwMDMuY3J0MFQGA1UdIARNMEswSQYEVR0g
# ADBBMD8GCCsGAQUFBwIBFjNodHRwOi8vd3d3Lm1pY3Jvc29mdC5jb20vcGtpb3Bz
# L0RvY3MvUmVwb3NpdG9yeS5odG0wDQYJKoZIhvcNAQEMBQADggIBABawPDJiODKH
# OkVCW1Ks8XtodxMW+GnjyK6Rl52pzsmbf9WZkZSY72fYQzGmvs5J1u1W2EQJ7mp4
# KMUN1LP+9dwj3Ng9/SCN6B4dZHPMSkU6R0hCxRdW6igWkGwHaRVT8cySh0mnjycl
# Y9hS7lvBevTLbJH2JAJCSugxGsSvVo0snfuvgQe4RUtLH5UoTtFjTkmX2vnRsZTh
# cYl4GqAei8aZnV+5UWAFhGlV/7ATRlOsNcaAtHG5TOcMkTVo0wAOPVaL6JUttQbz
# BJr835mKaQ52AsLeZpqOET7+oe+j3sqPyw6A6FcQNqXim9BQcMeBiOTtBX4YtNso
# LU1/0v5GBnzz+OGtyozfC4pgRne+tKMQNFkB+hZ4OvksLNaNSGpKFU71VKNrmwmK
# 3lJkWXDRq8dSd86PgiGTHf8NoFqfuFtLaTVz3JTtfUhTN2W3hXN8qSap7ExxbTCi
# /U6b0f4YofZfy0jUlY1hfykc/8wIjcWdwbQ7wWH9xoZYhuZPYJwdAtUeIbKdhBjw
# x5iMYVnvsOOpyjG57/wJ3MiflRV19JwPWjNnZNIIkGkNc3xgPMQs9oyeS7dB15mP
# bqT9hRuEYwApNtd5vtWZ62BxGj49+k0DMzemUxBd3iL8ZJVfg5DHdsSuoHDlyMBE
# 7OTV8GzemIT4SHmWYIedBTBp4lbW65RdMIIHKDCCBRCgAwIBAgITMwAAABgN65FV
# qYoAmAAAAAAAGDANBgkqhkiG9w0BAQwFADBjMQswCQYDVQQGEwJVUzEeMBwGA1UE
# ChMVTWljcm9zb2Z0IENvcnBvcmF0aW9uMTQwMgYDVQQDEytNaWNyb3NvZnQgSUQg
# VmVyaWZpZWQgQ29kZSBTaWduaW5nIFBDQSAyMDIxMB4XDTI2MDMyNjE4MTEzMloX
# DTMxMDMyNjE4MTEzMlowWjELMAkGA1UEBhMCVVMxHjAcBgNVBAoTFU1pY3Jvc29m
# dCBDb3Jwb3JhdGlvbjErMCkGA1UEAxMiTWljcm9zb2Z0IElEIFZlcmlmaWVkIENT
# IEFPQyBDQSAwMzCCAiIwDQYJKoZIhvcNAQEBBQADggIPADCCAgoCggIBAMiA2mA0
# VqKJ/ZVZ5Y/kjo+cVfEn+UHft8lHnkYK9HsYtkEyQGKNuIpXCCkEjfEzmd/jzjOc
# f+qdwn44KrrLeOCdBb5Hxl3tT7suOWuZyyRqXNJDSCEzESmcFbz8cezZXxknNCTo
# c/5IOxu+wvst2Uf947aXiaSeEMHCvRn9D3rpO8S2HlvyQLGPW+qJXhg22EsZGplH
# 27Z8r/IExa7zeno7i6jYR2D76AR7Dkgvu+eecoWqZKH9H288nLdYXVhxl7ABTHyx
# dk1SfHdmFWDn2XYumK0+LDMToUyoiypoS9V7czO4V3Zr+5YNkfpVsPJSJErvyYiD
# UNBgD3MMTLIEVw0j6fFVLOCW8vq7s9G42qBxXex/oQvHDz3KxAz9nhHWFEVZdGnI
# 5YooAq18EdOTRSc2I9zGYswxizyN5SM6J19U+NMivL9RXCfDF2WQrzlxl8EQxhn8
# ME07B2iY/jn1jWfyLMqRuGxr6niXD5xBXEBMEXH2CBHv0eGvJPscOak8u+Qm8Fnj
# BbgJbfZRPZIzIN7bycg5Teb6F8eVV4pwsFBzKblWhEOMhwJUju6qAZbY80wTRx96
# LzMLALLocKyywlYVLt6D9hsWGcBMlzJZ8yuQ24Bsx8w3w2mDxytLqNVWjDIPQYbn
# N2CL65BVxIr/rfyYDXERgremcihCA7T264MHAgMBAAGjggHcMIIB2DAOBgNVHQ8B
# Af8EBAMCAYYwEAYJKwYBBAGCNxUBBAMCAQAwHQYDVR0OBBYEFKRDDH92WqWF5z6N
# KA8MF6JFaXDGMFQGA1UdIARNMEswSQYEVR0gADBBMD8GCCsGAQUFBwIBFjNodHRw
# Oi8vd3d3Lm1pY3Jvc29mdC5jb20vcGtpb3BzL0RvY3MvUmVwb3NpdG9yeS5odG0w
# GQYJKwYBBAGCNxQCBAweCgBTAHUAYgBDAEEwEgYDVR0TAQH/BAgwBgEB/wIBADAf
# BgNVHSMEGDAWgBTZQSmwDw9jbO9p1/XNKZ6kSGow5jBwBgNVHR8EaTBnMGWgY6Bh
# hl9odHRwOi8vd3d3Lm1pY3Jvc29mdC5jb20vcGtpb3BzL2NybC9NaWNyb3NvZnQl
# MjBJRCUyMFZlcmlmaWVkJTIwQ29kZSUyMFNpZ25pbmclMjBQQ0ElMjAyMDIxLmNy
# bDB9BggrBgEFBQcBAQRxMG8wbQYIKwYBBQUHMAKGYWh0dHA6Ly93d3cubWljcm9z
# b2Z0LmNvbS9wa2lvcHMvY2VydHMvTWljcm9zb2Z0JTIwSUQlMjBWZXJpZmllZCUy
# MENvZGUlMjBTaWduaW5nJTIwUENBJTIwMjAyMS5jcnQwDQYJKoZIhvcNAQEMBQAD
# ggIBAHHHIFb5fqaF1GJLAP08wxZwZQCfHn9BTCc29l0UYRf7gNEMiv1YKHgLzvAe
# 3D6WDUPe7MrXQOy09fQsUsEUALe9YxhfgiZfCguGhHTGU3yZR2isnduCekIla4jX
# nfVnWsLA+5StKQHF84gYOTenYQJvcej/EeLk9FJH85Sta5AfBeJpcxO5e7chEt7P
# BWRmkWY3BhEPntH03HYX/Izu3M5jQeHSEYJpgQrfz/oWtLRJdp1dbINQJ+flc4YA
# JGNQKcfH4lBQbR/hIcP6JuWkAjSCX5kedWZ1dfEdNl5NrQJgIiEXEo/b3bazSDrM
# uZ6JXXctZSa239QXtOtZekyLb/RQ2eJoOgfuuc8ZFXnFVfy5fLixmKLhqzDOo8zt
# jv6bNytqepnwSNmTmCMuFDcDaxlqmuU67wJpGbJ9wiJUfvNV+AC+bzUxZcXOIB/u
# bLtA6+fIQU8Z12rwxJ8+19HLD9Sre4foqmhok0h89gfp9x5lKLndFq3UD2CsTGrd
# E6OGFKlNxyG4Ei0Aw1U/Ggo1tSb6JH9fdeQv71ZCCKePId76FctyVjy8AZcUPWnj
# Q+owikBiyYQkEUpb11/j//U3mhAOv8Vj0gEmX+hJL3v2Lmu1Ps1nP0q9itoI9EEa
# zRALL6xa+BBrRygzvRAlUt5XCZLFQ7/Sh3TD1CvLttIuvEagMIIHnjCCBYagAwIB
# AgITMwAAAAeHozSje6WOHAAAAAAABzANBgkqhkiG9w0BAQwFADB3MQswCQYDVQQG
# EwJVUzEeMBwGA1UEChMVTWljcm9zb2Z0IENvcnBvcmF0aW9uMUgwRgYDVQQDEz9N
# aWNyb3NvZnQgSWRlbnRpdHkgVmVyaWZpY2F0aW9uIFJvb3QgQ2VydGlmaWNhdGUg
# QXV0aG9yaXR5IDIwMjAwHhcNMjEwNDAxMjAwNTIwWhcNMzYwNDAxMjAxNTIwWjBj
# MQswCQYDVQQGEwJVUzEeMBwGA1UEChMVTWljcm9zb2Z0IENvcnBvcmF0aW9uMTQw
# MgYDVQQDEytNaWNyb3NvZnQgSUQgVmVyaWZpZWQgQ29kZSBTaWduaW5nIFBDQSAy
# MDIxMIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAsvDArxmIKOLdVHpM
# SWxpCFUJtFL/ekr4weslKPdnF3cpTeuV8veqtmKVgok2rO0D05BpyvUDCg1wdsoE
# tuxACEGcgHfjPF/nZsOkg7c0mV8hpMT/GvB4uhDvWXMIeQPsDgCzUGzTvoi76YDp
# xDOxhgf8JuXWJzBDoLrmtThX01CE1TCCvH2sZD/+Hz3RDwl2MsvDSdX5rJDYVuR3
# bjaj2QfzZFmwfccTKqMAHlrz4B7ac8g9zyxlTpkTuJGtFnLBGasoOnn5NyYlf0xF
# 9/bjVRo4Gzg2Yc7KR7yhTVNiuTGH5h4eB9ajm1OCShIyhrKqgOkc4smz6obxO+Hx
# KeJ9bYmPf6KLXVNLz8UaeARo0BatvJ82sLr2gqlFBdj1sYfqOf00Qm/3B4XGFPDK
# /H04kteZEZsBRc3VT2d/iVd7OTLpSH9yCORV3oIZQB/Qr4nD4YT/lWkhVtw2v2s0
# TnRJubL/hFMIQa86rcaGMhNsJrhysLNNMeBhiMezU1s5zpusf54qlYu2v5sZ5zL0
# KvBDLHtL8F9gn6jOy3v7Jm0bbBHjrW5yQW7S36ALAt03QDpwW1JG1Hxu/FUXJbBO
# 2AwwVG4Fre+ZQ5Od8ouwt59FpBxVOBGfN4vN2m3fZx1gqn52GvaiBz6ozorgIEjn
# +PhUXILhAV5Q/ZgCJ0u2+ldFGjcCAwEAAaOCAjUwggIxMA4GA1UdDwEB/wQEAwIB
# hjAQBgkrBgEEAYI3FQEEAwIBADAdBgNVHQ4EFgQU2UEpsA8PY2zvadf1zSmepEhq
# MOYwVAYDVR0gBE0wSzBJBgRVHSAAMEEwPwYIKwYBBQUHAgEWM2h0dHA6Ly93d3cu
# bWljcm9zb2Z0LmNvbS9wa2lvcHMvRG9jcy9SZXBvc2l0b3J5Lmh0bTAZBgkrBgEE
# AYI3FAIEDB4KAFMAdQBiAEMAQTAPBgNVHRMBAf8EBTADAQH/MB8GA1UdIwQYMBaA
# FMh+0mqFKhvKGZgEByfPUBBPaKiiMIGEBgNVHR8EfTB7MHmgd6B1hnNodHRwOi8v
# d3d3Lm1pY3Jvc29mdC5jb20vcGtpb3BzL2NybC9NaWNyb3NvZnQlMjBJZGVudGl0
# eSUyMFZlcmlmaWNhdGlvbiUyMFJvb3QlMjBDZXJ0aWZpY2F0ZSUyMEF1dGhvcml0
# eSUyMDIwMjAuY3JsMIHDBggrBgEFBQcBAQSBtjCBszCBgQYIKwYBBQUHMAKGdWh0
# dHA6Ly93d3cubWljcm9zb2Z0LmNvbS9wa2lvcHMvY2VydHMvTWljcm9zb2Z0JTIw
# SWRlbnRpdHklMjBWZXJpZmljYXRpb24lMjBSb290JTIwQ2VydGlmaWNhdGUlMjBB
# dXRob3JpdHklMjAyMDIwLmNydDAtBggrBgEFBQcwAYYhaHR0cDovL29uZW9jc3Au
# bWljcm9zb2Z0LmNvbS9vY3NwMA0GCSqGSIb3DQEBDAUAA4ICAQB/JSqe/tSr6t1m
# CttXI0y6XmyQ41uGWzl9xw+WYhvOL47BV09Dgfnm/tU4ieeZ7NAR5bguorTCNr58
# HOcA1tcsHQqt0wJsdClsu8bpQD9e/al+lUgTUJEV80Xhco7xdgRrehbyhUf4pkeA
# hBEjABvIUpD2LKPho5Z4DPCT5/0TlK02nlPwUbv9URREhVYCtsDM+31OFU3fDV8B
# mQXv5hT2RurVsJHZgP4y26dJDVF+3pcbtvh7R6NEDuYHYihfmE2HdQRq5jRvLE1E
# b59PYwISFCX2DaLZ+zpU4bX0I16ntKq4poGOFaaKtjIA1vRElItaOKcwtc04CBrX
# SfyL2Op6mvNIxTk4OaswIkTXbFL81ZKGD+24uMCwo/pLNhn7VHLfnxlMVzHQVL+b
# Ha9KhTyzwdG/L6uderJQn0cGpLQMStUuNDArxW2wF16QGZ1NtBWgKA8Kqv48M8Hf
# FqNifN6+zt6J0GwzvU8g0rYGgTZR8zDEIJfeZxwWDHpSxB5FJ1VVU1LIAtB7o9PX
# bjXzGifaIMYTzU4YKt4vMNwwBmetQDHhdAtTPplOXrnI9SI6HeTtjDD3iUN/7ygb
# ahmYOHk7VB7fwT4ze+ErCbMh6gHV1UuXPiLciloNxH6K4aMfZN1oLVk6YFeIJEok
# uPgNPa6EnTiOL60cPqfny+Fq8UiuZzGCGukwghrlAgEBMHEwWjELMAkGA1UEBhMC
# VVMxHjAcBgNVBAoTFU1pY3Jvc29mdCBDb3Jwb3JhdGlvbjErMCkGA1UEAxMiTWlj
# cm9zb2Z0IElEIFZlcmlmaWVkIENTIEFPQyBDQSAwMwITMwACXqZYf0Y01cTVVgAA
# AAJepjANBglghkgBZQMEAgEFAKCBtjAZBgkqhkiG9w0BCQMxDAYKKwYBBAGCNwIB
# BDAcBgorBgEEAYI3AgELMQ4wDAYKKwYBBAGCNwIBFTAvBgkqhkiG9w0BCQQxIgQg
# Kld7dFLdvZygPQIm94QeWCWq09RhnYWpKs5R8/oLpjgwSgYKKwYBBAGCNwIBDDE8
# MDqgNIAyAFAAeQB0AGgAbwBuACAAMwAuADEANQAuADAAYgAzACAAKABjAGYAMQA2
# AGEAMwAzACmhAoAAMA0GCSqGSIb3DQEBAQUABIIBgCggAm+GOcgl5yC9raFVQulQ
# fOL05R/sIyFsxxT/9IV/vVjCaFzK+de5V4vCXy+e5Wgi9LfxdLXeGY4cnmEtZNHN
# oT4qLPGe+egMjmG2x9jia/+0uAEE0a1NLud3ZpbgbYp+Pf+gMlmhmRj9CPML0DKL
# xVKrY1wkd6Q0J9KYsQXhPknWfCWdpHUqkzzrVnMcmLzupD8uuEp9aiG0B0Pz3jmS
# KDmnDXgHgzC1BKGmujHclgY6uT7IyUYUx+yeu6Dd3fgFZqDPj907uAqOxL3Jjxlw
# Hjl3HSN7O/2wOiykV/ebZpZLw3nhLPc7ie2n/Q739COAENGo0CeY4L3Y23vSrXXi
# EXM2vYMZn7eiKcWV5gMtEz6pgLT4JeVNRp/96fxZHOzg67K9czpxm0hUIWdg+UVZ
# ynooM2ApZZjxGLQgp37JZqknnkKBYql3IuAu2E9DY3u2mZwHMqNOK5ZQYWlCWZtl
# uF6RP08nq0NsLh7RDXnuhHw6DxPjqK66rfbfYV7/IaGCGBAwghgMBgorBgEEAYI3
# AwMBMYIX/DCCF/gGCSqGSIb3DQEHAqCCF+kwghflAgEDMQ8wDQYJYIZIAWUDBAIB
# BQAwggFiBgsqhkiG9w0BCRABBKCCAVEEggFNMIIBSQIBAQYKKwYBBAGEWQoDATAx
# MA0GCWCGSAFlAwQCAQUABCAi9iPUt3znOxb+rHcG2EyWTWrKOh7HewJVHMEr9Ca3
# pgIGahxW/IYZGBMyMDI2MDYyMzExMDYwNy43MjlaMASAAgH0oIHhpIHeMIHbMQsw
# CQYDVQQGEwJVUzETMBEGA1UECBMKV2FzaGluZ3RvbjEQMA4GA1UEBxMHUmVkbW9u
# ZDEeMBwGA1UEChMVTWljcm9zb2Z0IENvcnBvcmF0aW9uMSUwIwYDVQQLExxNaWNy
# b3NvZnQgQW1lcmljYSBPcGVyYXRpb25zMScwJQYDVQQLEx5uU2hpZWxkIFRTUyBF
# U046QTUwMC0wNUUwLUQ5NDcxNTAzBgNVBAMTLE1pY3Jvc29mdCBQdWJsaWMgUlNB
# IFRpbWUgU3RhbXBpbmcgQXV0aG9yaXR5oIIPITCCB4IwggVqoAMCAQICEzMAAAAF
# 5c8P/2YuyYcAAAAAAAUwDQYJKoZIhvcNAQEMBQAwdzELMAkGA1UEBhMCVVMxHjAc
# BgNVBAoTFU1pY3Jvc29mdCBDb3Jwb3JhdGlvbjFIMEYGA1UEAxM/TWljcm9zb2Z0
# IElkZW50aXR5IFZlcmlmaWNhdGlvbiBSb290IENlcnRpZmljYXRlIEF1dGhvcml0
# eSAyMDIwMB4XDTIwMTExOTIwMzIzMVoXDTM1MTExOTIwNDIzMVowYTELMAkGA1UE
# BhMCVVMxHjAcBgNVBAoTFU1pY3Jvc29mdCBDb3Jwb3JhdGlvbjEyMDAGA1UEAxMp
# TWljcm9zb2Z0IFB1YmxpYyBSU0EgVGltZXN0YW1waW5nIENBIDIwMjAwggIiMA0G
# CSqGSIb3DQEBAQUAA4ICDwAwggIKAoICAQCefOdSY/3gxZ8FfWO1BiKjHB7X55cz
# 0RMFvWVGR3eRwV1wb3+yq0OXDEqhUhxqoNv6iYWKjkMcLhEFxvJAeNcLAyT+XdM5
# i2CgGPGcb95WJLiw7HzLiBKrxmDj1EQB/mG5eEiRBEp7dDGzxKCnTYocDOcRr9Kx
# qHydajmEkzXHOeRGwU+7qt8Md5l4bVZrXAhK+WSk5CihNQsWbzT1nRliVDwunuLk
# X1hyIWXIArCfrKM3+RHh+Sq5RZ8aYyik2r8HxT+l2hmRllBvE2Wok6IEaAJanHr2
# 4qoqFM9WLeBUSudz+qL51HwDYyIDPSQ3SeHtKog0ZubDk4hELQSxnfVYXdTGncaB
# nB60QrEuazvcob9n4yR65pUNBCF5qeA4QwYnilBkfnmeAjRN3LVuLr0g0FXkqfYd
# Umj1fFFhH8k8YBozrEaXnsSL3kdTD01X+4LfIWOuFzTzuoslBrBILfHNj8RfOxPg
# juwNvE6YzauXi4orp4Sm6tF245DaFOSYbWFK5ZgG6cUY2/bUq3g3bQAqZt65Kcae
# wEJ3ZyNEobv35Nf6xN6FrA6jF9447+NHvCjeWLCQZ3M8lgeCcnnhTFtyQX3XgCoc
# 6IRXvFOcPVrr3D9RPHCMS6Ckg8wggTrtIVnY8yjbvGOUsAdZbeXUIQAWMs0d3cRD
# v09SvwVRd61evQIDAQABo4ICGzCCAhcwDgYDVR0PAQH/BAQDAgGGMBAGCSsGAQQB
# gjcVAQQDAgEAMB0GA1UdDgQWBBRraSg6NS9IY0DPe9ivSek+2T3bITBUBgNVHSAE
# TTBLMEkGBFUdIAAwQTA/BggrBgEFBQcCARYzaHR0cDovL3d3dy5taWNyb3NvZnQu
# Y29tL3BraW9wcy9Eb2NzL1JlcG9zaXRvcnkuaHRtMBMGA1UdJQQMMAoGCCsGAQUF
# BwMIMBkGCSsGAQQBgjcUAgQMHgoAUwB1AGIAQwBBMA8GA1UdEwEB/wQFMAMBAf8w
# HwYDVR0jBBgwFoAUyH7SaoUqG8oZmAQHJ89QEE9oqKIwgYQGA1UdHwR9MHsweaB3
# oHWGc2h0dHA6Ly93d3cubWljcm9zb2Z0LmNvbS9wa2lvcHMvY3JsL01pY3Jvc29m
# dCUyMElkZW50aXR5JTIwVmVyaWZpY2F0aW9uJTIwUm9vdCUyMENlcnRpZmljYXRl
# JTIwQXV0aG9yaXR5JTIwMjAyMC5jcmwwgZQGCCsGAQUFBwEBBIGHMIGEMIGBBggr
# BgEFBQcwAoZ1aHR0cDovL3d3dy5taWNyb3NvZnQuY29tL3BraW9wcy9jZXJ0cy9N
# aWNyb3NvZnQlMjBJZGVudGl0eSUyMFZlcmlmaWNhdGlvbiUyMFJvb3QlMjBDZXJ0
# aWZpY2F0ZSUyMEF1dGhvcml0eSUyMDIwMjAuY3J0MA0GCSqGSIb3DQEBDAUAA4IC
# AQBfiHbHfm21WhV150x4aPpO4dhEmSUVpbixNDmv6TvuIHv1xIs174bNGO/ilWMm
# +Jx5boAXrJxagRhHQtiFprSjMktTliL4sKZyt2i+SXncM23gRezzsoOiBhv14YSd
# 1Klnlkzvgs29XNjT+c8hIfPRe9rvVCMPiH7zPZcw5nNjthDQ+zD563I1nUJ6y59T
# bXWsuyUsqw7wXZoGzZwijWT5oc6GvD3HDokJY401uhnj3ubBhbkR83RbfMvmzdp3
# he2bvIUztSOuFzRqrLfEvsPkVHYnvH1wtYyrt5vShiKheGpXa2AWpsod4OJyT4/y
# 0dggWi8g/tgbhmQlZqDUf3UqUQsZaLdIu/XSjgoZqDjamzCPJtOLi2hBwL+KsCh0
# Nbwc21f5xvPSwym0Ukr4o5sCcMUcSy6TEP7uMV8RX0eH/4JLEpGyae6Ki8JYg5v4
# fsNGif1OXHJ2IWG+7zyjTDfkmQ1snFOTgyEX8qBpefQbF0fx6URrYiarjmBprwP6
# ZObwtZXJ23jK3Fg/9uqM3j0P01nzVygTppBabzxPAh/hHhhls6kwo3QLJ6No803j
# UsZcd4JQxiYHHc+Q/wAMcPUnYKv/q2O444LO1+n6j01z5mggCSlRwD9faBIySAcA
# 9S8h22hIAcRQqIGEjolCK9F6nK9ZyX4lhthsGHumaABdWzCCB5cwggV/oAMCAQIC
# EzMAAABWfo+dWAiO6WAAAAAAAFYwDQYJKoZIhvcNAQEMBQAwYTELMAkGA1UEBhMC
# VVMxHjAcBgNVBAoTFU1pY3Jvc29mdCBDb3Jwb3JhdGlvbjEyMDAGA1UEAxMpTWlj
# cm9zb2Z0IFB1YmxpYyBSU0EgVGltZXN0YW1waW5nIENBIDIwMjAwHhcNMjUxMDIz
# MjA0NjUxWhcNMjYxMDIyMjA0NjUxWjCB2zELMAkGA1UEBhMCVVMxEzARBgNVBAgT
# Cldhc2hpbmd0b24xEDAOBgNVBAcTB1JlZG1vbmQxHjAcBgNVBAoTFU1pY3Jvc29m
# dCBDb3Jwb3JhdGlvbjElMCMGA1UECxMcTWljcm9zb2Z0IEFtZXJpY2EgT3BlcmF0
# aW9uczEnMCUGA1UECxMeblNoaWVsZCBUU1MgRVNOOkE1MDAtMDVFMC1EOTQ3MTUw
# MwYDVQQDEyxNaWNyb3NvZnQgUHVibGljIFJTQSBUaW1lIFN0YW1waW5nIEF1dGhv
# cml0eTCCAiIwDQYJKoZIhvcNAQEBBQADggIPADCCAgoCggIBALSln5v7pdNu/3fE
# ZW/DJ/4NEFL7y6mNlbMt7SPFNrRUrKU2aJmTg9wR0/C5Efka4TCYG9VYwChTcrGi
# vXC0l4nzxkiAazwoLPT+MtuJayRJq1ekOc+AZqjISD62YRL2Z1qQkuBzu42Enov5
# 8Zgu/9RK/peS4Nz5ksW/HdiFXAEcUsNQeJsQelyNJ5HpfcGtXWG9sHxqaH62hZsW
# TsU/XjYbeCx9EQUlbnm2umTaY0v9ILX5u6oiIsj+qej0c002zJ1arB51f3f61tMx
# 8fkPkDWecFKipk2SQfYVPOd/tqV+aw3yt9rjWPf1gTgJs26oKRHUJG4jGr1DMlA0
# oZsnCL4B3UJ0ttO7E4/DPpCS97TnWoT7j6jMLGggoHX8MEMdDvUynuxUr2wBGLNQ
# J5XQpfyhxmQjlb1Dao8i9dCS3tP/hg/f8p6lxlhaVzo2rp72f3CkToYzeDOXuscd
# G9poqnD4ouP4otmYXimpZSRE+wipaRUobN8MoOhf36I0MULz521g+DcsepYY1o8J
# qC3MesNRUgrWrywpct9wS0UpU1OKilMWmvHe2DexKqZ/VztEmNLpjryhV61h+68Z
# fvYmonIrXZ005LAJ0Y73pHSk95YO5UTH5n2VPL1zYjdFGCc0/RI6o0ZtLjf4dKF8
# T4TXz2KnhW8j1xhsc2mFM+s8d6k3AgMBAAGjggHLMIIBxzAdBgNVHQ4EFgQUvrYz
# 8rurWf4eRrMi78s9R/hTSFowHwYDVR0jBBgwFoAUa2koOjUvSGNAz3vYr0npPtk9
# 2yEwbAYDVR0fBGUwYzBhoF+gXYZbaHR0cDovL3d3dy5taWNyb3NvZnQuY29tL3Br
# aW9wcy9jcmwvTWljcm9zb2Z0JTIwUHVibGljJTIwUlNBJTIwVGltZXN0YW1waW5n
# JTIwQ0ElMjAyMDIwLmNybDB5BggrBgEFBQcBAQRtMGswaQYIKwYBBQUHMAKGXWh0
# dHA6Ly93d3cubWljcm9zb2Z0LmNvbS9wa2lvcHMvY2VydHMvTWljcm9zb2Z0JTIw
# UHVibGljJTIwUlNBJTIwVGltZXN0YW1waW5nJTIwQ0ElMjAyMDIwLmNydDAMBgNV
# HRMBAf8EAjAAMBYGA1UdJQEB/wQMMAoGCCsGAQUFBwMIMA4GA1UdDwEB/wQEAwIH
# gDBmBgNVHSAEXzBdMFEGDCsGAQQBgjdMg30BATBBMD8GCCsGAQUFBwIBFjNodHRw
# Oi8vd3d3Lm1pY3Jvc29mdC5jb20vcGtpb3BzL0RvY3MvUmVwb3NpdG9yeS5odG0w
# CAYGZ4EMAQQCMA0GCSqGSIb3DQEBDAUAA4ICAQAOA6gFxLDtuo/y2uxYJ/0In4rf
# MbmXpKmee/mHvrB/4UU2xBIxmK2YLKsEf5VFHyghaW2RfJrGmT0CTkeGGFBFPF8o
# y65/GNYwkpqMYfZe7VokqHPyRQcN+eiQJsxhsXgQNhFksUbk69QLmXup2GjfP8LR
# ZIh3LPIDGncVwbOg8VYcruWJ4Sz0JH7pipt5RX7cBO6Ynle39ZbJJpYLAugHkhgs
# xj2VIAr3B+U7/0Hvc+2yCJkg90rs4TiMGj/nikE2H+u04n8iSpFkEnRn0wOinLuN
# ZPCweqDyvjC5NY28cSucD6i0i+tsYytOEgVxxCUhJ7BbdM8VpMT/5YHo9Q8alJ5q
# 2BHZMb8ykhyAKhVkmbpf+YSPrycbxT4bDUARJOHErpQ5CUKXHVYv4Jn/5hxTmIQw
# Y7GtebOC/trAYpd11f0/EYkeukPMWL0y0VsXdnVbKzqAsJ7FOFiHogtCYpwr9Vix
# xIe0Ms6/UUq+JCiS1naTWC4YI5KI05hJAIxTu++Ld8Qe3p27yBdBjrFdfcZwlM6v
# RBisrdIDLmqYSpTYyfmk6Y1jGQxqPhjirJ6fdx5n7ZpdEsqdxffjN8vsuliRlGaC
# GSattu4w44xJ3baVK4fQXT3VSH1SQ/wLvNUc4dOVBwIr6K0NzrPDxCxyIIjnfU1s
# 23YJhs3CC7f3XVUBETGCB0Iwggc+AgEBMHgwYTELMAkGA1UEBhMCVVMxHjAcBgNV
# BAoTFU1pY3Jvc29mdCBDb3Jwb3JhdGlvbjEyMDAGA1UEAxMpTWljcm9zb2Z0IFB1
# YmxpYyBSU0EgVGltZXN0YW1waW5nIENBIDIwMjACEzMAAABWfo+dWAiO6WAAAAAA
# AFYwDQYJYIZIAWUDBAIBBQCgggSbMBEGCyqGSIb3DQEJEAIPMQIFADAaBgkqhkiG
# 9w0BCQMxDQYLKoZIhvcNAQkQAQQwHAYJKoZIhvcNAQkFMQ8XDTI2MDYyMzExMDYw
# N1owLwYJKoZIhvcNAQkEMSIEIJEbeGoWSYno98y1jay2V3ytSlnGMMMt2ZimX5oR
# nwg2MIG5BgsqhkiG9w0BCRACLzGBqTCBpjCBozCBoAQgtgwzJU2k4/CVd4k4OV56
# XuAkh+tNeN2fl/aOTQYDDKgwfDBlpGMwYTELMAkGA1UEBhMCVVMxHjAcBgNVBAoT
# FU1pY3Jvc29mdCBDb3Jwb3JhdGlvbjEyMDAGA1UEAxMpTWljcm9zb2Z0IFB1Ymxp
# YyBSU0EgVGltZXN0YW1waW5nIENBIDIwMjACEzMAAABWfo+dWAiO6WAAAAAAAFYw
# ggNdBgsqhkiG9w0BCRACEjGCA0wwggNIoYIDRDCCA0AwggIoAgEBMIIBCaGB4aSB
# 3jCB2zELMAkGA1UEBhMCVVMxEzARBgNVBAgTCldhc2hpbmd0b24xEDAOBgNVBAcT
# B1JlZG1vbmQxHjAcBgNVBAoTFU1pY3Jvc29mdCBDb3Jwb3JhdGlvbjElMCMGA1UE
# CxMcTWljcm9zb2Z0IEFtZXJpY2EgT3BlcmF0aW9uczEnMCUGA1UECxMeblNoaWVs
# ZCBUU1MgRVNOOkE1MDAtMDVFMC1EOTQ3MTUwMwYDVQQDEyxNaWNyb3NvZnQgUHVi
# bGljIFJTQSBUaW1lIFN0YW1waW5nIEF1dGhvcml0eaIjCgEBMAcGBSsOAwIaAxUA
# /3P3KRUqkFmAXl4IMkSdmW72BBGgZzBlpGMwYTELMAkGA1UEBhMCVVMxHjAcBgNV
# BAoTFU1pY3Jvc29mdCBDb3Jwb3JhdGlvbjEyMDAGA1UEAxMpTWljcm9zb2Z0IFB1
# YmxpYyBSU0EgVGltZXN0YW1waW5nIENBIDIwMjAwDQYJKoZIhvcNAQELBQACBQDt
# 5H8aMCIYDzIwMjYwNjIzMDM0MjE4WhgPMjAyNjA2MjQwMzQyMThaMHMwOQYKKwYB
# BAGEWQoEATErMCkwCgIFAO3kfxoCAQAwBgIBAAIBbzAHAgEAAgISozAKAgUA7eXQ
# mgIBADA2BgorBgEEAYRZCgQCMSgwJjAMBgorBgEEAYRZCgMCoAowCAIBAAIDB6Eg
# oQowCAIBAAIDAYagMA0GCSqGSIb3DQEBCwUAA4IBAQB1yeK/FtLJcuNuQxmzU75r
# vHsHc000Aow+Rh5pCULslMvKoBKl6g8riLGwHN9drAbFzIF/umGIIYxPxeH+DYDB
# IBoc0PQwvoZZzyQM2sVgIcfrblmn8zk7N5nakMdS2mMr3F/zO6Q4ABpF5XBvUCg5
# KN6VtWkzmrpMtJkgc0lbZqOaTqpuTXyQpdzW9XdqL4d17zzSQeaitdLYMfCx2MWE
# RHdwi3Oz9FGLSo7adO+02j5bkgMRbw6qRrloonYsx6U5HIxYoWMFBuWVOnGYHjU9
# MqqWqtNzkxXGhxZ0pVXu0HTvoTGf9e2riXV0f5YYvbjmxvYIE6rla6z4lOelTfcL
# MA0GCSqGSIb3DQEBAQUABIICAKxU9LXLQYha5+d4AH6+Aih+DP1zgVhOBbiSUiNt
# 7+6D/SRMpyXvCzsg6jtt3Og0lYSURxRSnc8urGSO/jKdPIZEcV0eHt3wdNPvu314
# 6kQyss3v7/cJYCJC92z/gF889RIfKaz8A2Hq4tY7SUyFFiTTBwFPkq+SpF4gvljb
# 3/9TOBRXridSV7mUTUu4Gk3DvOQq9JGppNU7SVVW+R14cvGVKvbYnVumU1+48cIX
# muIU42k7K1vurmJo3SvAYUiRJ8Xmb0vCzJfE7Lap+IpJOQ7hZGH48e15H/wCpZsO
# fcVTcM3576bX9m5MswRopIrujiyXKGB44rVcZzcjNgIl+g1JM1ENXFhC5lmi6Qhp
# ZtFeJoYAeLKR0ZZ9DK5laDi87hDNTtfZLPApo3HgUyFHPwtPzXocWKchanXrFCUG
# +NBBTyQNbXmh1hAO+M8OLLz8Pbco9j7OOfRlZFAXXCl5L9bwguRRESzd45sXi2i6
# ZI8dnfreSIIWmIhywQX3saUza/7S/MtnoyKmptoCQSZiukXf/W/gqk9IKHK9+Jok
# BCrX094lqEEJhRdb43/A93gqBhspCERLCPccMj6UwHrI3lDSGFQ7D4Tll+vedFTQ
# yOLW8BKU1DNS177dC+VtFmlq7h/stMhR6sPyj8fDvxOEcCXIUK+NoZTFfT0z6HIl
# ESjn
# SIG # End signature block
