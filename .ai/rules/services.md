---
paths:
  - 'app/Services/**'
---

# Services

## Do not replay uncertain Enhance creates
Enhance create requests are guarded by persisted service intent markers and a client organization claim. A timeout, missing remote ID, or interrupted worker must be reconciled against Enhance before any create is repeated; never clear markers simply to make retry work. Checkout snapshots prices; payment confirmation must be admin-only, atomic, receipt-deduplicated, and enqueue provisioning after commit.
