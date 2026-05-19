# TODO — Taylor Distributor Locator

## Immediate — M6 Device QA (before merging to master)

- [ ] iOS Safari — tap a map pin, verify popup stays open and pans into view
- [ ] iOS Safari — swipe left on content area, verify list appears
- [ ] iOS Safari — swipe right on content area, verify map appears and scrolls into view
- [ ] Android Chrome — repeat all of the above
- [ ] Verify all interactive elements meet 44px tap target on both platforms
- [ ] Verify desktop layout is completely unaffected (both panels always visible, no toggle)
- [ ] Verify Google Maps provider (if used) — popup stays open on tap

## Current — M7 Gravity Forms Quote Modal

- [ ] **Manual step**: Configure reCAPTCHA v3 in GF Admin → Settings → reCAPTCHA (enter site key + secret, then enable v3 on Form 1 under Form Settings → Personal Data)
- [ ] Load the distributor locator page and confirm the modal appears on "Request Quote" click
- [ ] Confirm the GF form renders inside the modal with all fields
- [ ] Confirm the distributor ID hidden field is populated (check with browser devtools: `#input_1_7`)
- [ ] Submit the form and confirm AJAX submission works (no page reload)
- [ ] Confirm success message shows, then modal auto-closes after ~2.5 s
- [ ] Confirm modal closes on X button click
- [ ] Confirm modal closes on backdrop click
- [ ] Confirm modal closes on Escape key
- [ ] Confirm Tab key cycles through modal elements only (focus trap)
- [ ] Confirm mobile layout (modal slides up from bottom on ≤600px)
- [ ] Confirm desktop layout and all M1–M6 features still work
- [ ] Confirm no PHP errors in debug log after form submission
- [ ] Check GF entry saved correctly with distributor ID in field 7

## Git

- [ ] Commit M7 with message: `feat(m7): Gravity Forms quote modal — hidden distributor ID field, modal open/close, focus trap, AJAX submission`
- [ ] Merge `feature/m7-gravity-form-and-modal` to `staging` after QA sign-off

## Module Status Summary

| Module | Status |
|---|---|
| M1 | Complete |
| M2 | Complete |
| M3 | Complete |
| M4 | Complete |
| M5 | Hard-blocked (WPML not confirmed) |
| M6 | Complete — pending device QA |
| M7 | Complete — pending QA |
| M8 | Blocked until M7 |
| M9 | Blocked until M7 + M8 |
| M10 | Blocked until M1–M9 |

## Ongoing

- [ ] Update PROJECT_NOTES.md after each completed task.
- [ ] Update DEV_LOG.md after each session.
- [ ] Update TODO.md when tasks change.
- [ ] Update PHASE_PLAN.md when module status changes.
- [ ] Update DECISIONS.md when important decisions are made.