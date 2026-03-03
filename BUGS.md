# VoltexaHub — Live Bug & Polish List

Issues reported from live testing at voltexahub.com.
Add new items as found. Check off when fixed.

---

## 🐛 Bugs

- [x] **Avatar not showing on thread list** — fixed: avatar_path added to selects, author accessor added to Thread model with $appends
- [x] **Avatar not showing on forum index last poster** — fixed: avatar_path added to lastPostUser select
- [x] **Admin dashboard stats are hardcoded** — fixed: wired to real last_seen / DB counts
- [ ] **Joined date not working** — shows wrong value or missing on postbit/profile
- [ ] **Post date/time raw ISO format** — showing `2026-03-03T02:05:44.000000Z` instead of human-readable (e.g. "Mar 3, 2026 at 8:05 PM")

---

## ✨ Polish / UI

- [x] **Pinned thread separation** — fixed: visual divider + "Regular Threads" label between pinned and normal threads
- [x] **Store — remove payment type filter** — fixed: filter tabs removed, payment type shown on item card only
- [ ] **Postbit redesign** — style in rows: show credits, posts, threads, join date, group badge. Make it look cleaner and more structured.
- [ ] **Thread likes** — heart icon to like a thread, show a bottom container with avatars/names of who liked it (cap at reasonable amount, e.g. show first 10 then "+X more")

---

## 🔧 Features Pending

- [ ] **Usergroup manager in admin panel** — currently no way to create groups in admin UI. Need: create/edit/delete groups, set group name, color, badge label, permissions (can_pin, can_lock, can_upload_images, etc.), assign users to groups. Groups drive postbit badge + forum permissions.
- [ ] **Granular permission system** — per-group toggles for actions: pin, lock, delete, move threads, upload images, use signatures, bypass rate limits, etc.
