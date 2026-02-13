# CLAUDE.md

## Project Overview

CAH-Generator is a Cards Against Humanity custom card generator web app. Users create printable custom CAH cards with various expansions, mechanics, and custom icons. Hosted at http://mywastedlife.com/CAH.

## Tech Stack

- **Backend**: PHP (card generation via ImageMagick shell commands)
- **Frontend**: HTML5, Bootstrap 3.x, jQuery 1.10.1, Underscore.js
- **Image Processing**: ImageMagick (`convert` commands at 1200 DPI)
- **No package manager** — all vendor libraries are bundled in `/js/vendor/`

## Project Structure

```
index.html          # Main web interface
generator.php       # Backend card generation (PHP → ImageMagick)
build_templates.sh  # Bash script to build icon template variants
css/                # Bootstrap + minimal custom styles (main.css)
js/                 # main.js, plugins.js, vendor/ (jQuery, Bootstrap, Underscore, Modernizr)
img/                # Card templates, icons, expansion sets
fonts/              # HelveticaNeueBold.ttf (gitignored, proprietary) + Glyphicons
```

## Build Commands

### Build icon templates
```bash
./build_templates.sh -i <icon_path> -p <prefix>
```
Generates white/black card variants with mechanic overlays using ImageMagick.

## Key Architecture

### Card Generation Pipeline
1. **Frontend** (index.html + JS): Form validation, text processing (underline expansion, smart quotes, UTF-8), AJAX submission
2. **Backend** (generator.php): Receives form data → selects card template → processes text via Perl for UTF-8 → pipes to ImageMagick `convert` → generates PNGs → bundles into ZIP

### Text Processing (JS side)
- Underline expansion: `_` → full line, consecutive `_`s → custom length
- Smart quotes → Unicode hex escapes
- `\n` → line breaks on cards
- Max 30 cards per batch

### Security
- `escapeshellcmd()` for shell injection prevention
- `getimagesize()` for upload validation

## External Dependencies

- **ImageMagick** must be installed on the server
- **HelveticaNeueBold.ttf** font file required but gitignored (proprietary)

## No Tests

No test framework or test files exist. Testing is manual via the live site.

## Conventions

- Commit messages are short, imperative descriptions
- No CI/CD — manual deployment to shared PHP hosting
- Generated files go to `/files/` (gitignored)
- Logs: `card_log.txt` (CSV of generated text), `error_log` (both gitignored)
