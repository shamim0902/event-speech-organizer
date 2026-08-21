#!/bin/bash

# Packs a distributable plugin zip into builds/event-speech-organizer.zip.
# Only runtime files are included — src/, node_modules/ and dev tooling stay
# out. Run `npm run build:zip` to produce production assets and the zip in
# one step.

set -e

# Colors for output
BLUE='\033[0;34m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

PLUGIN_SLUG="event-speech-organizer"
SOURCE_DIR="$(pwd)"
BUILDS_DIR="$SOURCE_DIR/builds"
OUTPUT_FILE="$BUILDS_DIR/${PLUGIN_SLUG}.zip"

# Whitelist: everything the plugin needs at runtime, nothing else.
INCLUDE_ITEMS=(
    "event-speech-organizer.php"
    "index.php"
    "includes"
    "assets"
    "languages"
    "CHANGELOG.md"
)

echo -e "${BLUE}📦 Creating ZIP archive...${NC}"

mkdir -p "$BUILDS_DIR"
[[ -f "$OUTPUT_FILE" ]] && rm "$OUTPUT_FILE"

# Stage the payload under the plugin slug so the archive root is always
# ${PLUGIN_SLUG}/, whatever the checkout directory happens to be called.
STAGE_DIR="$(mktemp -d)"
trap 'rm -rf "$STAGE_DIR"' EXIT
mkdir -p "$STAGE_DIR/$PLUGIN_SLUG"

for item in "${INCLUDE_ITEMS[@]}"; do
    if [[ ! -e "$SOURCE_DIR/$item" ]]; then
        echo -e "${YELLOW}⚠️  Skipping missing whitelist entry: ${item}${NC}"
        continue
    fi
    cp -R "$SOURCE_DIR/$item" "$STAGE_DIR/$PLUGIN_SLUG/"
done

# macOS litter has no place in a release.
find "$STAGE_DIR" -name '.DS_Store' -delete

(cd "$STAGE_DIR" && zip -rq "$OUTPUT_FILE" "$PLUGIN_SLUG")

SIZE="$(du -h "$OUTPUT_FILE" | cut -f1 | tr -d ' ')"
echo -e "${GREEN}✅ Created builds/${PLUGIN_SLUG}.zip (${SIZE})${NC}"
