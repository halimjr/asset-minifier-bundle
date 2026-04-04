# Security Policy

## Supported versions

| Version | Supported |
|---|---|
| 1.7.x | ✓ |
| 1.6.x | ✓ |
| < 1.6 | ✗ |

## Reporting a vulnerability

Please do not report security vulnerabilities through public GitHub issues.

Send a description to **hello@halimjr.com** with the subject line `[asset-minifier-bundle] Security Vulnerability`. Include as much detail as you can: the affected version, a description of the issue, and steps to reproduce if applicable.

You can expect an acknowledgement within 48 hours and a resolution or mitigation plan within 7 days.

## Scope

The main attack surface of this bundle is the esbuild binary download. Relevant protections already in place:

- Downloads use HTTPS with TLS peer verification
- The SHA-512 integrity hash is fetched from the npm registry metadata API and verified against the tarball before extraction
- The binary is never written to disk if the hash does not match
- File locking prevents race conditions during concurrent downloads
