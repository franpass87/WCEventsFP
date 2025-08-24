#!/bin/bash

# Script to analyze potentially obsolete files in WCEventsFP
set -e

echo "# OBSOLETE FILES ANALYSIS"
echo "Generated on: $(date)"
echo ""

# Exclusion patterns
EXCLUDE_PATHS="./vendor ./node_modules ./.git ./tests ./assets/generated"
EXCLUDE_FIND_ARGS=""
for path in $EXCLUDE_PATHS; do
    EXCLUDE_FIND_ARGS="$EXCLUDE_FIND_ARGS -not -path \"$path/*\""
done

# Get all files excluding the specified directories
FILES=$(find . -type f -not -path "./vendor/*" -not -path "./node_modules/*" -not -path "./.git/*" -not -path "./tests/*" -not -path "./assets/generated/*")

# Function to check if a file is referenced
check_references() {
    local file="$1"
    local basename_file=$(basename "$file")
    local name_no_ext="${basename_file%.*}"
    local dirname_file=$(dirname "$file")
    
    # For PHP classes, check composer.json autoloading and class usage
    if [[ "$file" == *".php" ]]; then
        # Extract potential class name from file
        local class_name="$name_no_ext"
        
        # Check if it's autoloaded via composer PSR-4
        if grep -q "includes/" composer.json && [[ "$file" == *"includes/"* ]]; then
            # Likely autoloaded, check for class usage
            if grep -r -l --include="*.php" --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=.git \
               -e "\\\\$class_name" \
               -e "new $class_name" \
               -e "use.*$class_name" \
               -e "$class_name::" \
               . >/dev/null 2>&1; then
                return 0
            fi
        fi
        
        # Check for direct includes/requires
        if grep -r -l --include="*.php" --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=.git \
           -e "include.*$basename_file" \
           -e "require.*$basename_file" \
           -e "include.*${file#./}" \
           -e "require.*${file#./}" \
           . >/dev/null 2>&1; then
            return 0
        fi
    fi
    
    # For JS/CSS files, check for enqueuing
    if [[ "$file" == *".js" ]] || [[ "$file" == *".css" ]]; then
        local handle="$name_no_ext"
        if grep -r -l --include="*.php" --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=.git \
           -e "wp_enqueue.*$handle" \
           -e "wp_register.*$handle" \
           -e "wp_enqueue.*$basename_file" \
           -e "wp_register.*$basename_file" \
           . >/dev/null 2>&1; then
            return 0
        fi
    fi
    
    # General reference check
    local patterns=(
        "$basename_file"
        "${file#./}"
    )
    
    for pattern in "${patterns[@]}"; do
        # Check in all relevant file types
        if grep -r -l --include="*.php" --include="*.js" --include="*.css" --include="*.md" --include="*.txt" --include="*.json" \
           --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=.git --exclude-dir=tools \
           -F "$pattern" \
           . >/dev/null 2>&1; then
            return 0
        fi
    done
    
    return 1
}

# Function to identify file type and potential issues
analyze_file() {
    local file="$1"
    local basename_file=$(basename "$file")
    
    # Check for obvious temporary/backup patterns
    if [[ "$basename_file" =~ \.(bak|old|orig|tmp)$ ]] || [[ "$basename_file" =~ ~$ ]]; then
        echo "TEMP/BACKUP: $file"
        return
    fi
    
    # Check for OS artifacts
    if [[ "$basename_file" == ".DS_Store" ]] || [[ "$basename_file" == "Thumbs.db" ]] || [[ "$basename_file" =~ \.log$ ]]; then
        echo "OS_ARTIFACT: $file"
        return
    fi
    
    # Check for duplicate dist files
    if [[ "$basename_file" =~ \.dist$ ]]; then
        local main_file="${file%.dist}"
        if [[ -f "$main_file" ]]; then
            echo "DUPLICATE_DIST: $file (main file exists: $main_file)"
            return
        fi
    fi
    
    # Check references
    if check_references "$file"; then
        echo "REFERENCED: $file"
    else
        echo "UNREFERENCED: $file"
    fi
}

echo "## Analysis Results"
echo ""

# Analyze all files
while IFS= read -r file; do
    analyze_file "$file"
done <<< "$FILES"

echo ""
echo "## Analysis Complete"