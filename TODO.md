# TODO — Taylor Distributor Locator

## Immediate — M6 Device QA (before merging to master)

- [ ] iOS Safari — tap a map pin, verify popup stays open and pans into view
- [ ] iOS Safari — swipe left on content area, verify list appears
- [ ] iOS Safari — swipe right on content area, verify map appears and scrolls into view
- [ ] Android Chrome — repeat all of the above
- [ ] Verify all interactive elements meet 44px tap target on both platforms
- [ ] Verify desktop layout is completely unaffected (both panels always visible, no toggle)
- [ ] Verify Google Maps provider (if used) — popup stays open on tap

## Next — M7 Gravity Forms Quote Modal

- [ ] Confirm Gravity Forms is installed and active in the target environment
- [ ] Confirm form IDs before coding — do not assume
- [ ] Plan M7 implementation against current M3 stub (`tdl:quote-requested` event already dispatched)
- [ ] Create branch `feature/m7-quote-modal` before starting

## Git

- [ ] Commit M6 with message: `feat(m6): mobile enhancements — pin popup fix, map/list toggle, swipe, pan-to-bottom, 44px tap targets`
- [ ] Merge `feature/m4-csv-service-tool` to `master` after QA sign-off
- [ ] Create `feature/m7-quote-modal` from updated `master`

## Module Status Summary

| Module | Status |
|---|---|
| M1 | Complete |
| M2 | Complete |
| M3 | Complete |
| M4 | Complete |
| M5 | Hard-blocked (WPML not confirmed) |
| M6 | Complete — pending device QA |
| M7 | Ready to start |
| M8 | Blocked until M7 |
| M9 | Blocked until M7 + M8 |
| M10 | Blocked until M1–M9 |

## Ongoing

- [ ] Update PROJECT_NOTES.md after each completed task.
- [ ] Update DEV_LOG.md after each session.
- [ ] Update TODO.md when tasks change.
- [ ] Update PHASE_PLAN.md when module status changes.
- [ ] Update DECISIONS.md when important decisions are made.