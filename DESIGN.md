---
status: approved
owner: Design
last_verified: 2026-11-02
source_of_truth_for:
  - visual language
  - design tokens
  - component behavior
  - graph canvas
related:
  - docs/SCREENS.md
  - PRD.md
  - docs/adr/0007-graph-visualization-stack.md
---

# Design System

**Direction:** technical blueprint / data terminal.
**Feeling:** precise, instrumented, calm under pressure. A tool an on-call engineer trusts at 02:00, not a marketing dashboard.

The interface is dense but never cramped. Data is the hero. Color is reserved for severity, state, and one deliberate accent. Chrome recedes into graphite; the graph and the report carry the visual weight.

---

## 1. Principles

1. **Precision over decoration.** Every border, label, and number earns its place.
2. **Density with air.** Compact rows and panels, but clear grouping and 24–32 px section rhythm.
3. **Severity is the only loud color.** Critical red, warning amber, info blue. The accent is for action, never for status.
4. **Show provenance.** Inferred edges are visibly different from declared edges. Confidence is never hidden.
5. **No dead ends.** Every empty, failed, or stale state offers the next action.
6. **Keyboard is a first-class input.** The graph has a complete list equivalent.
7. **Motion explains, never entertains.** 120–180 ms, no bounce, no infinite decoration.

---

## 2. Anti-patterns (MUST NOT)

- Purple/blue gradient buttons or gradient text.
- Glassmorphism, frosted panels, or blurred backgrounds.
- Cards with 16–24 px radii and drop-shadow soup.
- Emoji as icons or status markers.
- A marketing hero inside the authenticated app.
- Spinners as the only loading signal.
- Color-only severity or status.
- Centered long-form paragraphs in data views.
- Fake terminal typing effects.
- Charts without axes, labels, or a table fallback.
- `animate-pulse` skeletons that shift layout.

---

## 3. Color tokens

Dark is the only app theme. A light palette exists for report printing.

### 3.1 Surfaces and text

| Token | Hex | Usage |
|---|---|---|
| `--ink-950` | `#070A0D` | App background, graph canvas |
| `--ink-900` | `#0B1015` | Sidebar, top bar |
| `--surface` | `#10171E` | Panels, table background |
| `--surface-raised` | `#16212B` | Cards, nodes, menus |
| `--surface-hover` | `#1B2833` | Row hover, control hover |
| `--border` | `#22303C` | Default 1px border |
| `--border-strong` | `#324657` | Selected, focused containers |
| `--text` | `#E8EFF6` | Primary text |
| `--text-muted` | `#96A7B6` | Secondary text, labels |
| `--text-faint` | `#647888` | Metadata, timestamps |
| `--accent` | `#D8FF4F` | Primary action, active nav, focus ring |
| `--accent-ink` | `#0A0F03` | Text on accent |
| `--accent-soft` | `rgba(216,255,79,0.12)` | Active nav background, selection tint |

### 3.2 Severity and state

| Token | Hex | Usage |
|---|---|---|
| `--critical` | `#FF5C5C` | Critical changes, destructive actions |
| `--critical-bg` | `rgba(255,92,92,0.12)` | Critical chip/row background |
| `--warning` | `#F5A524` | Warnings, pending attention |
| `--warning-bg` | `rgba(245,165,36,0.12)` | Warning background |
| `--info` | `#5CA8FF` | Informational changes, links |
| `--info-bg` | `rgba(92,168,255,0.12)` | Info background |
| `--success` | `#3DD68C` | Completed, approved, healthy |
| `--success-bg` | `rgba(61,214,140,0.12)` | Success background |
| `--neutral` | `#8B9AA6` | Unknown, disabled, neutral |
| `--focus` | `#D8FF4F` | 2px ring, 2px offset |

Contrast requirements:

- `--text` on `--surface` ≥ 12:1.
- `--text-muted` on `--surface` ≥ 5.5:1.
- `--text-faint` only for non-essential metadata; ≥ 4.5:1 against `--surface`.
- Accent text is always `--accent-ink`, never white.
- Severity text uses the severity color on its tinted background only when contrast ≥ 4.5:1; otherwise use `--text` with a colored dot.

### 3.3 Print palette

| Token | Hex |
|---|---|
| `--paper` | `#F7F7F4` |
| `--paper-ink` | `#12181D` |
| `--paper-muted` | `#56636E` |
| `--paper-border` | `#D7D9D3` |
| `--paper-critical` | `#B42318` |
| `--paper-warning` | `#8A4B00` |
| `--paper-info` | `#1D4ED8` |

### 3.4 Graph canvas

| Element | Style |
|---|---|
| Background | `--ink-950` with a 24 px dot grid at `rgba(255,255,255,0.045)` |
| Node fill | `--surface-raised` |
| Node border | `--border` |
| Node selected | `--accent` 1px border + 3px `--accent-soft` halo |
| Node impacted | severity border + 4px severity halo |
| Declared edge | `#5B7183`, 1.5 px, arrowhead |
| Inferred edge | `#5B7183`, 1.5 px, dashed `4 3` |
| Critical edge | `--critical`, 2 px |
| Dimmed (focus mode) | 20% opacity |

---

## 4. Typography

**Families**

- UI: **IBM Plex Sans** (`400`, `500`, `600`)
- Data: **IBM Plex Mono** (`400`, `500`)

Loaded with `next/font/google`, `display: swap`, subset latin. Fallbacks: `ui-sans-serif`, `system-ui`, `sans-serif`; `ui-monospace`, `SFMono-Regular`, `monospace`.

**Scale**

| Token | Size / line | Weight | Tracking | Usage |
|---|---|---|---|---|
| `display` | 40 / 44 | 600 | -0.02em | Login wordmark, print cover |
| `h1` | 28 / 34 | 600 | -0.01em | Page title |
| `h2` | 20 / 26 | 600 | -0.01em | Section title |
| `h3` | 16 / 22 | 600 | 0 | Panel title |
| `body` | 14 / 21 | 400 | 0 | Default |
| `body-strong` | 14 / 21 | 500 | 0 | Row emphasis |
| `small` | 12 / 17 | 400 | 0 | Secondary text |
| `micro` | 11 / 14 | 500 | 0.06em uppercase | Table headers, labels |
| `mono` | 12 / 18 | 400 | 0 | IDs, paths, methods, JSON |
| `mono-sm` | 11 / 16 | 400 | 0 | Metadata, hashes |

Rules:

- Numbers in tables are tabular: `font-variant-numeric: tabular-nums`.
- Methods are uppercase mono: `GET`, `POST`.
- Paths are mono and never wrap mid-parameter if avoidable; use `overflow-wrap: anywhere` as a last resort.
- IDs and hashes are truncated in the middle with a copy button.
- No text below 11 px. No justified text. No all-caps paragraphs.

---

## 5. Space, radius, borders, elevation

**Spacing** — 4 px base: `4, 8, 12, 16, 20, 24, 32, 40, 56, 72`.
Section gap: 32 px. Panel padding: 20–24 px. Table cell: 12 × 16 px. Form field gap: 16 px.

**Radius**

| Element | Radius |
|---|---|
| Button, input, select | 2 px |
| Badge, chip | 2 px |
| Panel, card, node | 4 px |
| Dialog, drawer | 4 px |
| Avatar | 999 px |

**Borders** — 1 px everywhere; `--border` default, `--border-strong` for selected or focused containers. Never remove a border and rely on shadow.

**Elevation** — shadows only for overlays:

```css
--shadow-overlay: 0 16px 48px rgba(0, 0, 0, 0.55);
```

Panels, cards, and tables never float.

---

## 6. Layout

```text
┌──────────────┬──────────────────────────────────────────────┐
│ sidebar 232  │ top bar 56                                   │
│              ├──────────────────────────────────────────────┤
│ nav          │ content padding 32, max-width 1440           │
│ workspace    │                                              │
│ user         │                                              │
└──────────────┴──────────────────────────────────────────────┘
```

- Sidebar collapsible to 64 px; persists in local storage.
- Content max width 1440 px; report view max 880 px.
- Tables use full width; no centered container for data.
- Detail pages use a 2-column layout: main 2/3, context rail 1/3 (320 px) on ≥ 1280 px.
- Breakpoints: `sm 640`, `md 768`, `lg 1024`, `xl 1280`, `2xl 1536`.
- On < 1024 px the sidebar becomes a drawer; context rail stacks below main.
- Touch targets ≥ 44 px on touch devices; 32–36 px is acceptable for mouse-dense controls.

---

## 7. Components

All components live in `frontend/src/components/ui` and are built on shadcn/ui primitives, restyled to these tokens. Default shadcn shadows, radii, and colors MUST be removed.

### 7.1 Button

| Variant | Background | Text | Border | Usage |
|---|---|---|---|---|
| Primary | `--accent` | `--accent-ink` | none | One per view |
| Secondary | transparent | `--text` | `--border-strong` | Supporting action |
| Ghost | transparent | `--text-muted` | none | Nav, icon actions |
| Danger | `--critical-bg` | `--critical` | `--critical` 40% | Destructive confirm only |

Heights: 32 (sm), 36 (md), 40 (lg). Horizontal padding: 12 / 16. Focus: 2 px accent ring, 2 px offset. Disabled: 40% opacity, no pointer events. Loading: label replaced by `Working…`, width locked.

### 7.2 Inputs

- Height 36, background `--ink-900`, 1 px border, radius 2.
- Placeholder `--text-faint`.
- Focus: border `--accent`, ring `--accent` 2 px at 20% opacity.
- Error: border `--critical`, message below in 12 px `--critical`.
- Labels are 12 px `--text-muted`, always above the field. No placeholder-as-label.
- Helper text 12 px `--text-faint`.

### 7.3 Severity chip

```text
● Critical    ● Warning    ● Info
```

- 20 px height, 6 px horizontal padding, 2 px radius, tinted background.
- 6 px dot in the severity color.
- Label in `micro` style, title case.
- Never icon-only; never color-only.
- Table rows may also use a 2 px left border in the severity color.

### 7.4 Method badge

Monospace uppercase, 3 letters, 18 px height, tinted background.

| Method | Color |
|---|---|
| GET | `--info` |
| POST | `--success` |
| PUT / PATCH | `--warning` |
| DELETE | `--critical` |
| HEAD / OPTIONS | `--neutral` |

### 7.5 Table

- Header: `micro`, `--text-faint`, 40 px height, sticky.
- Row: 44 px min, 1 px bottom border, hover `--surface-hover`.
- Numeric columns right-aligned and tabular.
- Severity column uses a chip, not a colored dot alone.
- Sort control shows direction with an arrow and `aria-sort`.
- Pagination: cursor-based, "Previous / Next" + `Showing 1–25`.
- Empty state inside the table body, not a separate blank panel.

### 7.6 Panel / card

- Background `--surface`, 1 px border, 4 px radius, no shadow.
- Header: 48 px, `h3` left, actions right, 1 px bottom border.
- Body padding 20.
- Cards are for discrete objects (a service, a report), not for wrapping every section.

### 7.7 Diff viewer

- Split view ≥ 1024 px, unified below.
- Before column tinted `--critical-bg`; after column tinted `--success-bg`.
- Removed lines prefixed `−`, added lines `+`, in mono.
- Line numbers in `--text-faint`, non-selectable.
- Syntax highlighting limited to JSON tokens: keys `--text`, strings `--accent`, numbers `--info`, booleans `--warning`.
- Collapsible unchanged context with `… 12 unchanged lines`.

### 7.8 Async status

A job is never represented by a spinner alone.

```text
[ Queued ]  Waiting for a worker
[ Running ] Analyzing 42 endpoints…
[ Failed ]  Diff failed. ERR-1304. Retry
[ Done ]    Completed 2 minutes ago
```

- Status chip + one sentence + action.
- Polling interval: 2 s, backing off to 5 s after 30 s.
- Announce changes with `aria-live="polite"`.

### 7.9 Dialogs and drawers

- Dialog max width 480, radius 4, overlay `rgba(7,10,13,0.8)`.
- Destructive dialogs require typing the resource name only for irreversible actions (token revoke, spec delete).
- Drawer from the right, 320 px, for graph node details and filters.
- Escape closes; focus returns to the trigger.

### 7.10 Token reveal-once

- Modal with a warning banner: "This token will not be shown again."
- Monospace value in a read-only field with a Copy button.
- Copy action announces "Copied" via `aria-live`.
- Close button label: "I stored it".

### 7.11 Empty states

Structure: 24 px line icon, `h3`, one sentence, primary action.

Examples:

- Specs: "No specs yet. Upload an OpenAPI document to build your first graph." → **Upload spec**
- Reports: "No impact reports yet. Run a diff and select breaking changes." → **View specs**
- Graph: "No consumers found. Add `x-consumers` to the spec or declare a binding." → **Add binding**

No illustrations of people. No "Oops!". No exclamation marks.

---

## 8. Graph canvas

The graph is the signature surface. It must look like an instrument, not a toy.

### 8.1 Projection

The API returns `Service`, `APIEndpoint`, `SchemaComponent`, and `Consumer` nodes. The explorer displays:

- **Service nodes** always.
- **Endpoint nodes** when a service is selected or "Show endpoints" is on.
- **Schema nodes** only when "Show schemas" is on.
- **Consumer nodes** are collapsed into edges from the consumer service to the endpoint.

### 8.2 Service node

```text
┌─────────────────────────────┐
│ billing            Payments │
│ 12 endpoints · 3 consumers  │
└─────────────────────────────┘
```

- Width 220, min height 72, radius 4.
- Name 14/600; owner 12 muted; counts 11 mono faint.
- Left 3 px bar in the highest severity affecting the service.
- Impacted nodes get a 4 px severity halo.
- Selected: accent border + halo, plus a 1 px inner line.
- Handles: 8 px circles on left/right, visible on hover/selection.

### 8.3 Endpoint node

- Width 190, height 52.
- Method badge + path in mono 11, truncated with a tooltip.
- Critical changes add a red left bar.

### 8.4 Edges

- Solid = declared or manual binding.
- Dashed = inferred.
- Arrow points from consumer to provider for `CONSUMES`; for `DEPENDS_ON`, from dependent to dependency.
- Edge label on hover: source, confidence, evidence.
- Parallel edges are bundled; a count badge appears when > 3.

### 8.5 Layout

- `elkjs` layered, `direction: RIGHT`, `nodeSpacing: 48`, `layerSpacing: 120`, `edgeRouting: ORTHOGONAL`.
- Providers left, consumers right.
- Layout runs in a web worker. Re-layout only when the node set changes, not on pan/zoom.
- Initial fit view with 48 px padding; max zoom 2, min 0.2.
- Above 500 rendered nodes: aggregate endpoints into service nodes and show a banner with "Show all (may be slow)".
- Cycles are allowed in layout; never hide them. A cycle badge appears on involved nodes.

### 8.6 Controls

Top-left: zoom in, zoom out, fit, focus selected.
Top-right: search, filters (severity, inferred edges, show endpoints, show schemas), list view toggle.
Bottom-left: legend (solid/dashed, severity).
Right drawer: selected node details, owner, endpoints, consumers, actions.

### 8.7 Keyboard and accessibility

- A visually hidden "Graph list" toggle is always the first focusable element in the canvas.
- List view renders a table: service, owner, relation, depth, severity, endpoints.
- `Tab` moves through nodes in layout order; `Enter` selects; `Escape` clears; `+`/`-` zoom; `0` fits.
- Each node has an accessible name: "billing, provider, 12 endpoints, 3 consumers, critical impact".
- The canvas has `role="application"` with instructions in `aria-describedby`.
- Reduced motion disables layout animation.

---

## 9. Motion

| Token | Duration | Easing | Usage |
|---|---|---|---|
| `fast` | 120 ms | `cubic-bezier(0.2, 0.7, 0.2, 1)` | Hover, focus, chips |
| `base` | 180 ms | same | Drawer, dialog, tab underline |
| `layout` | 200 ms | same | Graph initial layout only |

- No bounce, spring, or looping animation.
- No skeleton shimmer; use static skeleton blocks at the final dimensions.
- `prefers-reduced-motion: reduce` sets all durations to 0 and disables layout animation.
- Page transitions are instant; no route-level fade.

---

## 10. Accessibility

- WCAG 2.2 AA minimum.
- Focus visible on every interactive element; never `outline: none` without a replacement.
- Landmarks: `header`, `nav`, `main`, `aside`.
- One `h1` per page; headings do not skip levels.
- Severity is conveyed by dot + label + position, not color alone.
- Tables have `<caption>` or `aria-label`, and proper `th scope`.
- Icon-only buttons have `aria-label` and a tooltip.
- Async changes announce via `aria-live`.
- Forms: label, description, error linked with `aria-describedby`; error summary at the top on submit.
- Color contrast checked in both themes.
- Zoom to 200% without loss of content.
- The report print view is readable in grayscale.

---

## 11. Iconography

- Lucide, 16 px in dense UI, 20 px in navigation.
- Stroke 1.5; no filled icons.
- Icons never replace a text label in primary actions.
- Status uses shapes + color: circle (neutral), triangle (warning), octagon (critical) when an icon is needed.

---

## 12. Data display rules

| Data | Format |
|---|---|
| Timestamps | `2 Nov 2026, 14:03 UTC`; relative ("2 min ago") with absolute in `title` |
| Counts | `1,204` with thousands separators |
| Severity | Title case chip |
| Methods | Uppercase mono |
| Paths | Mono, no trailing slash |
| IDs | First 8 + `…` + last 4, copy button |
| Hashes | First 10 + `…`, copy button, full value in tooltip |
| Durations | `< 1 s`, `1.2 s`, `2 m 14 s` |
| Percentages | Integer, no decimals unless < 1% |
| Empty value | `—`, never blank |

---

## 13. Voice and copy

- Direct, technical, calm.
- Sentence case for headings and buttons.
- Buttons are verbs: "Upload spec", "Run diff", "Generate report", "Approve report".
- Errors state what happened and what to do: "Graph is stale. Rebuild it, then retry."
- No exclamation marks, no "Oops", no "Uh oh", no emoji.
- Use the glossary: "impact report", "service", "consumer", "owner team".
- Avoid "please" in UI copy.

---

## 14. Implementation tokens

Define tokens once in `frontend/src/app/globals.css` and expose them to Tailwind v4 via `@theme`.

```css
@theme {
  --color-ink-950: #070a0d;
  --color-ink-900: #0b1015;
  --color-surface: #10171e;
  --color-surface-raised: #16212b;
  --color-surface-hover: #1b2833;
  --color-border: #22303c;
  --color-border-strong: #324657;
  --color-text: #e8eff6;
  --color-text-muted: #96a7b6;
  --color-text-faint: #647888;
  --color-accent: #d8ff4f;
  --color-accent-ink: #0a0f03;
  --color-critical: #ff5c5c;
  --color-warning: #f5a524;
  --color-info: #5ca8ff;
  --color-success: #3dd68c;
  --color-neutral: #8b9aa6;
  --radius-control: 2px;
  --radius-panel: 4px;
  --font-sans: var(--font-plex-sans), ui-sans-serif, system-ui, sans-serif;
  --font-mono: var(--font-plex-mono), ui-monospace, monospace;
}
```

Rules:

- No raw hex values in components; use tokens.
- No arbitrary Tailwind values for color, radius, or font size.
- Spacing may use the Tailwind scale (multiples of 4).
- Dark mode is the default and only mode; do not add a theme toggle in MVP.
- Print styles live in `@media print` and use the paper palette.

---

## 15. Design QA checklist

- [ ] No gradient, glass, or oversized radius introduced.
- [ ] Primary action is unique per view and uses the accent.
- [ ] Severity uses chip + label, not color alone.
- [ ] All states exist: loading, empty, error, stale, partial.
- [ ] Focus ring visible on every interactive element.
- [ ] Keyboard-only path works for the graph and the report.
- [ ] Text contrast passes AA in the app and print palette.
- [ ] Touch targets ≥ 44 px on mobile.
- [ ] Tables are tabular and right-aligned where numeric.
- [ ] No placeholder used as a label.
- [ ] Copy matches the glossary and the voice rules.
- [ ] Reduced motion honored.
- [ ] No layout shift on async transitions.
