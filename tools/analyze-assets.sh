#!/bin/bash

# CSS/JS/Images Asset Analysis for WCEventsFP
# Maps enqueued handles to files and identifies unmapped assets

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
echo "🎨 CSS/JS/Images Asset Analysis for WCEventsFP"
echo "=============================================="

echo ""
echo "📋 PHASE 1: Enqueued Handles Analysis"
echo "-------------------------------------"

# Find all wp_enqueue_style calls
echo "CSS Enqueue Analysis:"
grep -r --include="*.php" -n "wp_enqueue_style" "$PROJECT_ROOT" | while read -r line; do
    echo "  $line"
done

echo ""
echo "JS Enqueue Analysis:"
# Find all wp_enqueue_script calls
grep -r --include="*.php" -n "wp_enqueue_script" "$PROJECT_ROOT" | while read -r line; do
    echo "  $line"
done

echo ""
echo "📁 PHASE 2: Asset Files Inventory"
echo "----------------------------------"

echo "CSS Files Found:"
find "$PROJECT_ROOT/assets/css" -name "*.css" 2>/dev/null | while read -r file; do
    filename=$(basename "$file")
    # Check if this file is referenced anywhere
    if grep -r --include="*.php" -q "$filename" "$PROJECT_ROOT" 2>/dev/null; then
        echo "  ✅ $file (REFERENCED)"
    else
        echo "  ❌ $file (ORPHANED)"
    fi
done

echo ""
echo "JS Files Found:"
find "$PROJECT_ROOT/assets/js" -name "*.js" 2>/dev/null | while read -r file; do
    filename=$(basename "$file")
    # Check if this file is referenced anywhere (PHP files or webpack)
    if grep -r --include="*.php" -q "$filename" "$PROJECT_ROOT" 2>/dev/null || grep -q "$filename" "$PROJECT_ROOT/webpack.config.js" 2>/dev/null; then
        echo "  ✅ $file (REFERENCED)"
    else
        echo "  ❌ $file (ORPHANED)"
    fi
done

echo ""
echo "Image Files Found:"
find "$PROJECT_ROOT/assets" -name "*.png" -o -name "*.jpg" -o -name "*.jpeg" -o -name "*.svg" -o -name "*.gif" -o -name "*.webp" 2>/dev/null | while read -r file; do
    filename=$(basename "$file")
    # Check if referenced in PHP, JS, CSS, or markdown
    if grep -r --include="*.php" --include="*.js" --include="*.css" --include="*.md" -q "$filename" "$PROJECT_ROOT" 2>/dev/null; then
        echo "  ✅ $file (REFERENCED)"
    else
        echo "  ❌ $file (ORPHANED)"
    fi
done

echo ""
echo "🔍 PHASE 3: Handle to File Mapping"
echo "-----------------------------------"

echo "Extracting handle patterns from enqueue calls..."

# Extract CSS handles
echo "CSS Handle Mapping:"
grep -r --include="*.php" "wp_enqueue_style" "$PROJECT_ROOT" | sed -n "s/.*wp_enqueue_style(\s*['\"]([^'\"]*)['\"].*/\1/p" | sort -u | while read -r handle; do
    if [[ -n "$handle" ]]; then
        # Look for corresponding files
        matching_files=$(find "$PROJECT_ROOT/assets" -name "*$handle*" -o -name "*$(echo $handle | tr '-' '_')*" 2>/dev/null)
        if [[ -n "$matching_files" ]]; then
            echo "  ✅ Handle: '$handle' → Files: $matching_files"
        else
            echo "  ⚠️  Handle: '$handle' → No matching files found"
        fi
    fi
done

echo ""
echo "JS Handle Mapping:"
grep -r --include="*.php" "wp_enqueue_script" "$PROJECT_ROOT" | sed -n "s/.*wp_enqueue_script(\s*['\"]([^'\"]*)['\"].*/\1/p" | sort -u | while read -r handle; do
    if [[ -n "$handle" ]]; then
        # Look for corresponding files
        matching_files=$(find "$PROJECT_ROOT/assets" -name "*$handle*" -o -name "*$(echo $handle | tr '-' '_')*" 2>/dev/null)
        if [[ -n "$matching_files" ]]; then
            echo "  ✅ Handle: '$handle' → Files: $matching_files"
        else
            echo "  ⚠️  Handle: '$handle' → No matching files found"
        fi
    fi
done

echo ""
echo "📊 SUMMARY"
echo "----------"
echo "Analysis complete. Check above for orphaned assets marked with ❌"