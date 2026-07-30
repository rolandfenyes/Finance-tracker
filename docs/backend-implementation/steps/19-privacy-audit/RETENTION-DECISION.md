# Step 19 Retention Decision

Status: approved by the product owner on 2026-07-30.

## Approved option A

- Retention remains configuration-gated. The application does not invent production retention periods.
- Account exports remain disabled unless the operator explicitly configures both the private artifact lifetime and signed-download URL lifetime.
- The signed-download URL lifetime must not exceed the artifact lifetime.
- Audit events, normalized-email-hash suppression records, and backup data are not automatically purged.
- Backup deletion and restore-window behavior remain a Step 21 production-readiness gate and are not implemented in Step 19.
- A legal-policy version or effective-date record must not be created until owner or counsel supplies the policy.

The configured export artifact lifetime controls API access and object metadata. Production object lifecycle enforcement remains an operator-owned storage configuration that must be approved and verified in Step 21.
