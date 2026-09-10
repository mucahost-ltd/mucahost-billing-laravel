# WHMCS Clone — Implementation Roadmap

> **Project:** Laravel 13 + React (Inertia v3) + TypeScript billing platform
> **Status:** Phase 3 underway — client management has started

---

## Phase 1 — Core Infrastructure ✅ (Done)

| Feature | Status |
|---|---|
| React Starter Kit (Inertia v3, TypeScript, Tailwind) | ✅ |
| Client auth (login, register, logout) | ✅ |
| Team management (multi-tenant admin) | ✅ |
| Product catalog (groups, products, pricing, billing cycles) | ✅ |
| Checkout flow (order, invoice, payment confirmation) | ✅ |
| Order provisioning (Enhance API + manual module) | ✅ |
| Admin billing dashboard (orders, invoices, services) | ✅ |
| Currency model | ✅ |

---

## Phase 2 — Support System 🎫

**Goal:** Full WHMCS-style support ticket system for clients and staff.

- [x] Support department CRUD (admin)
- [x] Client ticket creation (subject, department, priority, service link)
- [x] Client ticket list + detail view (replies thread)
- [x] Client reply to ticket
- [x] Client close ticket
- [x] Admin ticket queue (filter by department/status/priority + search + pagination)
- [x] Admin ticket view + reply + status change (open/answered/customer-reply/closed)
- [x] Ticket assignment to staff
- [x] Ticket notifications (email on new reply)
- [x] Ticket attachments (private multi-file upload/download on ticket creation and replies)

**Implemented:** `SupportDepartmentController`, `AdminTicketController`, `Client\TicketController`, private ticket attachments, pages `support/index`, `support/show`, `client/ticket/*`, sidebar link, and feature tests (`SupportTicketTest`).

**Notifications:** `TicketCreated` (queued mail to all admins on new ticket), `TicketReplied` (queued mail to client on staff reply, to assigned staff on client reply).

---

## Phase 3 — Client Management (Admin) 👥

**Goal:** Full client lifecycle management.

- [x] Client list with search/filter/pagination
- [x] Client detail page (profile, services, invoices, transactions, tickets)
- [x] Client create/edit (admin)
- [x] Client suspend/activate/close
- [x] Client notes (internal)
- [ ] Client email verification + password reset (admin-initiated)

---

## Phase 4 — Billing & Recurring 💰

**Goal:** Automated recurring billing engine.

- [ ] Recurring invoice generation (cron/scheduler based on next_due_date)
- [ ] Payment reminders (email before/after due date)
- [ ] Transaction management (record payment, refund, credit)
- [ ] Client credit balance (add/remove)
- [ ] Tax rules (per-country/per-state VAT)
- [ ] Late fees
- [ ] Invoice PDF download

---

## Phase 5 — Domain Management 🌐

**Goal:** Domain registration/transfer/renewal.

- [ ] Domain pricing (per-TLD)
- [ ] Domain order flow (register/transfer/renew)
- [ ] Domain management (nameservers, WHOIS, EPP code)
- [ ] Domain auto-renewal
- [ ] Domain status sync (cron)

---

## Phase 6 — Server Management 🖥️

**Goal:** Server infrastructure management.

- [ ] Server CRUD (name, hostname, IP, status, module)
- [ ] Server groups
- [ ] Server status monitoring (ping/API health)
- [ ] Server assignment to products

---

## Phase 7 — Admin Dashboard & Reports 📊

**Goal:** Business intelligence.

- [ ] Revenue report (daily/monthly/yearly)
- [ ] Client report (new/suspended/active)
- [ ] Order report (by product/status)
- [ ] Product performance (top sellers)
- [ ] Ticket report (volume, response time)

---

## Phase 8 — Notifications & Email 📧

**Goal:** Automated communication.

- [x] Admin-managed SMTP delivery settings (encrypted password, `.env` fallback, test email)
- [ ] Email template management
- [ ] Event-based notifications (invoice created, payment received, ticket reply, service provisioned)
- [ ] Activity log (admin actions)

---

## Phase 9 — Advanced Features 🚀

**Goal:** WHMCS parity features.

- [ ] Promotions/coupons (recurring discounts)
- [ ] Affiliate program
- [ ] Knowledgebase (articles, categories)
- [ ] Announcements
- [ ] Network status page
- [ ] Multi-language support
- [ ] API (WHMCS-style external API)

---

## Suggested Build Order

1. **Phase 2** — Support Tickets (highest client-facing value, models already exist)
2. **Phase 3** — Client Management (admin needs client control)
3. **Phase 4** — Recurring Billing (core revenue engine)
4. **Phase 5** — Domains (if domain business is in scope)
5. **Phase 6-9** — Infrastructure, reports, notifications, advanced

---

## Current Focus

**Phase 3 — Client Management (Admin)** is underway. Client profile CRUD, status controls,
and internal notes are now wired. Next: **admin email verification and password reset
controls**, followed by Phase 4 recurring billing.
