#!/bin/bash

# Orphan File Checker for WCEventsFP - Simplified Version
# Focuses on assets and common problem files
# Exit code 1 if orphans found, 0 if clean

set -e

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OBSOLETE_FILES_DOC="$PROJECT_ROOT/docs/OBSOLETE_FILES.md"

echo "🔍 Orphan File Checker for WCEventsFP (Simplified)"
echo "=================================================="

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Quick reference check function
has_references() {
    local filename="$1"
    grep -r -l --include="*.php" --include="*.js" --include="*.css" --include="*.md" --include="*.json" \
        --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=.git --exclude-dir=tools \
        -F "$filename" "$PROJECT_ROOT" >/dev/null 2>&1
}

# Check if documented in OBSOLETE_FILES.md
is_documented_obsolete() {
    local filename="$1"
    [[ -f "$OBSOLETE_FILES_DOC" ]] && grep -q "$filename" "$OBSOLETE_FILES_DOC" 2>/dev/null
}

ORPHANED_FILES=()

echo "Checking asset files..."

# Check CSS files
for css_file in $(find assets/css -name "*.css" 2>/dev/null || echo ""); do
    if [[ -f "$css_file" ]]; then
        filename=$(basename "$css_file")
        if ! has_references "$filename" && ! is_documented_obsolete "$filename"; then
            ORPHANED_FILES+=("$css_file")
        fi
    fi
done

# Check JS files
for js_file in $(find assets/js -name "*.js" 2>/dev/null || echo ""); do
    if [[ -f "$js_file" ]]; then
        filename=$(basename "$js_file")
        if ! has_references "$filename" && ! is_documented_obsolete "$filename"; then
            ORPHANED_FILES+=("$js_file")
        fi
    fi
done

# Check image files
for img_file in $(find assets -name "*.png" -o -name "*.jpg" -o -name "*.svg" -o -name "*.gif" 2>/dev/null || echo ""); do
    if [[ -f "$img_file" ]]; then
        filename=$(basename "$img_file")
        if ! has_references "$filename" && ! is_documented_obsolete "$filename"; then
            ORPHANED_FILES+=("$img_file")
        fi
    fi
done

# Check root level files for common problem patterns
for pattern in "*.html" "*.txt" "*.xml.dist" "*.neon.dist"; do
    for file in $(find . -maxdepth 1 -name "$pattern" 2>/dev/null || echo ""); do
        if [[ -f "$file" ]]; then
            filename=$(basename "$file")
            if ! has_references "$filename" && ! is_documented_obsolete "$filename"; then
                ORPHANED_FILES+=("$file")
            fi
        fi
    done
done

echo ""
echo "📊 Scan Results:"
echo "  Orphaned files found: ${#ORPHANED_FILES[@]}"

if [[ ${#ORPHANED_FILES[@]} -eq 0 ]]; then
    echo -e "${GREEN}✅ No orphaned files detected in key areas!${NC}"
    exit 0
else
    echo -e "${RED}❌ Found ${#ORPHANED_FILES[@]} potentially orphaned file(s):${NC}"
    echo ""
    
    for file in "${ORPHANED_FILES[@]}"; do
        echo -e "${YELLOW}  • $file${NC}"
    done
    
    echo ""
    echo -e "${YELLOW}⚠️  Action Required:${NC}"
    echo "Add these files to docs/OBSOLETE_FILES.md or ensure they are properly referenced."
    echo ""
    
    exit 1
fi