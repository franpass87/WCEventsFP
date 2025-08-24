#!/bin/bash

# Orphan File Checker for WCEventsFP
# Detects newly orphaned files that should be added to OBSOLETE_FILES.md
# Exit code 1 if orphans found, 0 if clean

set -e

SCRIPT_DIR="$(dirname "$0")"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
OBSOLETE_FILES_DOC="$PROJECT_ROOT/docs/OBSOLETE_FILES.md"

echo "🔍 Orphan File Checker for WCEventsFP"
echo "======================================"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Files to scan (extensions)
SCAN_EXTENSIONS=("php" "js" "css" "png" "svg" "jpg" "jpeg" "webp" "gif")

# Exclusion patterns
EXCLUDE_DIRS=("vendor" "node_modules" ".git" "tests" "assets/generated" "build")

# Files already documented as removed or acceptable
KNOWN_EXCLUSIONS=()

# Function to check if file is referenced
is_file_referenced() {
    local file_path="$1"
    local filename=$(basename "$file_path")
    local name_no_ext="${filename%.*}"
    
    # Quick check - if filename appears anywhere in relevant files, consider it referenced
    if grep -r -l --include="*.php" --include="*.js" --include="*.css" --include="*.md" --include="*.json" \
        --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=.git --exclude-dir=tools \
        -F "$filename" "$PROJECT_ROOT" >/dev/null 2>&1; then
        return 0
    fi
    
    # For PHP files in includes/, they might be autoloaded
    if [[ "$file_path" == *"includes/"*.php ]]; then
        # Check if class name appears (simple heuristic)
        if grep -r -l --include="*.php" \
            --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=.git \
            "$name_no_ext" "$PROJECT_ROOT" >/dev/null 2>&1; then
            return 0
        fi
    fi
    
    return 1
}

# Function to check if file is in OBSOLETE_FILES.md
is_documented_obsolete() {
    local filename="$1"
    if [[ -f "$OBSOLETE_FILES_DOC" ]]; then
        grep -q "$filename" "$OBSOLETE_FILES_DOC" 2>/dev/null
        return $?
    fi
    return 1
}

# Build find command with exclusions
FIND_CMD="find $PROJECT_ROOT -type f"
for exclude_dir in "${EXCLUDE_DIRS[@]}"; do
    FIND_CMD="$FIND_CMD -not -path \"*/$exclude_dir/*\""
done

# Add extension filters
EXT_FILTER=""
for ext in "${SCAN_EXTENSIONS[@]}"; do
    if [[ -z "$EXT_FILTER" ]]; then
        EXT_FILTER="-name \"*.$ext\""
    else
        EXT_FILTER="$EXT_FILTER -o -name \"*.$ext\""
    fi
done

FIND_CMD="$FIND_CMD \\( $EXT_FILTER \\)"

echo "Scanning for orphaned files..."
echo "Extensions: ${SCAN_EXTENSIONS[*]}"
echo "Excluding directories: ${EXCLUDE_DIRS[*]}"
echo ""

# Execute find and process results
ORPHANED_FILES=()
SCANNED_COUNT=0

while IFS= read -r file; do
    ((SCANNED_COUNT++))
    
    # Skip if file is known to be excluded
    skip_file=false
    for exclusion in "${KNOWN_EXCLUSIONS[@]}"; do
        if [[ "$file" == *"$exclusion"* ]]; then
            skip_file=true
            break
        fi
    done
    
    if $skip_file; then
        continue
    fi
    
    if ! is_file_referenced "$file"; then
        if ! is_documented_obsolete "$(basename "$file")"; then
            ORPHANED_FILES+=("$file")
        fi
    fi
done < <(eval "$FIND_CMD")

echo "📊 Scan Results:"
echo "  Files scanned: $SCANNED_COUNT"
echo "  Orphaned files found: ${#ORPHANED_FILES[@]}"

if [[ ${#ORPHANED_FILES[@]} -eq 0 ]]; then
    echo -e "${GREEN}✅ No orphaned files detected. Repository is clean!${NC}"
    exit 0
else
    echo -e "${RED}❌ Found ${#ORPHANED_FILES[@]} orphaned file(s):${NC}"
    echo ""
    
    for file in "${ORPHANED_FILES[@]}"; do
        echo -e "${YELLOW}  • $file${NC}"
    done
    
    echo ""
    echo -e "${YELLOW}⚠️  Action Required:${NC}"
    echo "Either:"
    echo "1. Add these files to docs/OBSOLETE_FILES.md if they are truly obsolete"
    echo "2. Ensure they are properly referenced in the codebase"
    echo "3. Add them to KNOWN_EXCLUSIONS in this script if they are false positives"
    echo ""
    
    exit 1
fi