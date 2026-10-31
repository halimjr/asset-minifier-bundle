# Security Policy

## Supported versions

| Version | Supported |
|---|---|
| 1.9.x | ✓ |
| 1.8.x | ✓ |
| < 1.8 | ✗ |

## Reporting a vulnerability

Please do not report security vulnerabilities through public GitHub issues.

Send a description to **hello@halimjr.com** with the subject line `[asset-minifier-bundle] Security Vulnerability`. Include as much detail as you can: the affected version, a description of the issue, and steps to reproduce if applicable.

You can expect an acknowledgement within 48 hours and a resolution or mitigation plan within 7 days.

## Scope

The main attack surface of this bundle is the esbuild binary download. Relevant protections already in place:

- Downloads use HTTPS with TLS peer verification
- For the default esbuild version, the tarball is verified against SHA-512 hashes pinned in the bundle's source (`EsbuildBinary::KNOWN_INTEGRITY`), so a compromised registry or tampered response cannot supply a matching hash
- For a custom `esbuild_version` that is not pinned, the hash is fetched from the npm registry metadata API. This catches corrupted downloads but not a compromised registry, so prefer the default version or set `binary_path` to a binary you have verified yourself
- The binary is never written to disk if the hash does not match or the download is incomplete
- File locking prevents race conditions during concurrent downloads
- Cleanup of old versions never follows symlinks out of the binary directory
