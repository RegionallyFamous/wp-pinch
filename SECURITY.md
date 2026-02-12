# Security Policy

**Website:** [wp-pinch.com](https://wp-pinch.com) | **Repository:** [GitHub](https://github.com/RegionallyFamous/wp-pinch)

WP Pinch handles sensitive data including API tokens and webhook URLs. Security is a top priority for this project, and we take all reports seriously.

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 2.0.x   | Yes                |
| 1.0.x   | Yes                |
| < 1.0   | No                 |

## Reporting a Vulnerability

If you discover a security vulnerability in WP Pinch, please report it responsibly. **Do not open a public GitHub issue for security vulnerabilities.**

### How to Report

- **Email:** Send a detailed report to [me@nickhamze.com](mailto:me@nickhamze.com)
- **GitHub:** Use [GitHub's private vulnerability reporting](https://github.com/RegionallyFamous/wp-pinch/security/advisories/new)

### What to Include

- A description of the vulnerability and its potential impact
- Steps to reproduce the issue
- The version of WP Pinch affected
- Any relevant configuration details (WordPress version, PHP version, hosting environment)
- Proof-of-concept code, if available
- Your suggested fix, if you have one

### Response Timeline

- **Acknowledgment:** Within 48 hours of your report
- **Status update:** Within 7 days, including an initial assessment and expected timeline for a fix
- **Resolution:** We aim to release a patch as quickly as possible, depending on the complexity of the issue

## Disclosure Policy

We follow a coordinated disclosure process:

1. The reporter submits the vulnerability privately.
2. We acknowledge receipt and begin investigation.
3. We develop and test a fix.
4. We release the fix and publish a security advisory.
5. The reporter is free to disclose the vulnerability publicly after the fix is released.

We will credit reporters in the security advisory and changelog unless they prefer to remain anonymous.

## Scope

The following areas are of particular interest:

- Authentication and authorization bypasses
- Exposure of API tokens or webhook secrets
- Injection vulnerabilities (SQL, XSS, CSRF)
- Privilege escalation via WP-CLI commands or REST API endpoints
- Data leakage through audit logs or analytics

## Contact

For security-related inquiries, contact [me@nickhamze.com](mailto:me@nickhamze.com).
