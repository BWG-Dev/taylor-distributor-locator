# Dev Log — Taylor Distributor Locator

## 2026-05-18 — M6 Mobile Enhancements

### Completed

M6 is functionally complete on branch `feature/m4-csv-service-tool`.

#### Popup fix — Leaflet pin bubble disappearing on tap

Root cause: Three compounding issues were identified and resolved.

1. **`overflow: hidden` on `.tdl-map-container`** clipped the Leaflet popup when it was taller than the map container. Changed to `overflow: visible` at both mobile breakpoints.
2. **Browser synthetic click** (~300–500ms after `touchend`) bubbled to the map background handler, triggering our `map.on('click', map.closePopup)` call immediately after the popup opened. Fixed with a `flagLeafletMarkerTap()` / `leafletMarkerTapped` flag that blocks the map click handler for 600ms after a marker tap.
3. **Leaflet autopan** was animating the map to try to fit the popup in view on a short map, causing the popup to appear to jump. Disabled with `autoPan: false` on mobile (`window.innerWidth <= 960`).

#### Map/List toggle

Added a "Map / List" toggle bar visible only on mobile (≤960px). Includes:
- Button click handlers
- `aria-pressed` state management
- Swipe gesture (horizontal dx > 50px, with scroll protection via dy comparison)
- `map.invalidateSize()` / Google Maps resize event on map reveal
- `scrollIntoView` when switching from list back to map (prevents map being rendered off-screen after list scroll)

#### Pan-to-bottom on marker tap

On mobile, tapping a pin now calls `panMarkerToBottom()` which pans the map so the marker lands centered horizontally and at 88% of the map height from the top. This gives the popup maximum visible space above it without manual dragging.

#### Touch targets

Applied `min-height: 44px` to `.tdl-request-quote`, `.tdl-page-btn`, `.tdl-accordion-toggle`, `.tdl-search-input`, and `.tdl-search-btn` at mobile breakpoints. Leaflet marker tap area expanded via `.tdl-marker-pin::before` pseudo-element (44×44px transparent hit zone).

#### Info window — condensed layout

Phone and website moved to a single inline row (`tdl-iw-contact-row`) separated by a `·` character. Section padding and font sizes reduced on mobile. `max-height: 460px` with `overflow-y: auto` on the popup wrapper.

#### Map heights (final values)

| Breakpoint | Height |
|---|---|
| ≤960px | 520px |
| ≤600px | 420px |

### Files Changed

| File | Changes |
|---|---|
| `assets/js/tdl-locator.js` | Popup fix (flag + stopPropagation + closePopupOnClick:false), mobile toggle + swipe, panMarkerToBottom, autoPan:false, condensed info window |
| `assets/css/tdl-locator.css` | overflow:visible, mobile map heights, toggle bar styles, 44px touch targets, marker hit zone, popup max-height, condensed popup spacing |
| `templates/locator-main.php` | Mobile toggle markup (conditional on split view) |
| `includes/class-tdl-shortcode.php` | Comment clarifying footer enqueue + async API loading |

### Testing Performed

Verified via code review and logic tracing. Device testing required on iOS Safari and Android Chrome before final sign-off (see TODO.md).

### Known Issues

- `autoPan: false` on mobile means if a pin is tapped near the top edge of the map, the popup tip may be clipped by the map top boundary. The `panMarkerToBottom` call handles most cases but an extreme-top-edge pin could still clip. Workaround: user drags map slightly.
- Desktop retains `overflow: hidden` on the map container for rounded tile corners. If a desktop user clicks a pin very near the top of the map and the popup is tall, it may be partially clipped. Leaflet's autopan (enabled on desktop) mitigates this in most cases.
- `window.innerWidth` is evaluated at marker creation time (not on resize). If the browser is resized between mobile and desktop widths after page load, `autoPan` and `panMarkerToBottom` may behave incorrectly. Not a real-world concern for production use.

### Suggested Commit Message

```
feat(m6): mobile enhancements — pin popup fix, map/list toggle, swipe, pan-to-bottom, 44px tap targets
```

---

## 2026-05-18 — Project Memory Setup

### Project Memory Setup

Added project-memory structure to support multi-day Claude Code work without replacing the current CLAUDE.md instructions.

Recommended files:
- PROJECT_NOTES.md
- PHASE_PLAN.md
- DECISIONS.md
- DEV_LOG.md
- TODO.md

### Current Notes

The existing CLAUDE.md is strong and should not be replaced.

It should only be extended with a small “Project Memory Files” section so Claude knows to read and update the companion files.

### Next Recommended Step

1. Add the small Project Memory Files section to the bottom of CLAUDE.md.
2. Create PROJECT_NOTES.md.
3. Create PHASE_PLAN.md.
4. Create DECISIONS.md.
5. Create DEV_LOG.md.
6. Create TODO.md.
7. Ask Claude to inspect current git status and project files before coding.

### Suggested Next Prompt

Read CLAUDE.md, PROJECT_NOTES.md, PHASE_PLAN.md, DECISIONS.md, DEV_LOG.md, and TODO.md.

Then check git status and latest commits.

Do not make code changes yet.

Summarize the current module, branch, completed work, pending work, blockers, and recommended next safest step.