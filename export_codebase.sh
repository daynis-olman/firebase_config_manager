#!/bin/bash

# Output file
OUTPUT_FILE="codebase.txt"

# Get the module root directory
BASE_DIR="$(pwd)"

# Clear the output file
> "$OUTPUT_FILE"

echo "Exporting only the relevant Drupal module files..."
echo "Root directory: $BASE_DIR"

# Strictly limit search to the current directory, max depth 3, and exclude unnecessary files
find "$BASE_DIR" -mindepth 1 -maxdepth 3 -type f \
  ! -path "$BASE_DIR/vendor/*" \
  ! -path "$BASE_DIR/.git/*" \
  ! -path "$BASE_DIR/exported_files_debug.txt" \
  ! -path "$BASE_DIR/$OUTPUT_FILE" \
  ! -path "$BASE_DIR/export_codebase.sh" \
  ! -name "composer.lock" \
  ! -name "LICENSE" \
  ! -name "*.png" \
  ! -name "*.jpg" \
  ! -name "*.gif" \
  ! -name "*.mp4" \
  ! -name "*.mp3" \
  ! -name "*.zip" \
  ! -name "*.tar.gz" \
  ! -name "*.log" \
  ! -name "*.cache" \
  -size -5M | while read -r file; do

  # Add file path as a comment at the top
  echo "# File: $file" >> "$OUTPUT_FILE"
  echo "" >> "$OUTPUT_FILE"

  # Append file contents
  cat "$file" >> "$OUTPUT_FILE" || echo "[Error reading file: $file]" >> "$OUTPUT_FILE"

  # Add end file comment
  echo "" >> "$OUTPUT_FILE"
  echo "# end file" >> "$OUTPUT_FILE"
  echo "" >> "$OUTPUT_FILE"
done

echo "Export complete: $(du -h "$OUTPUT_FILE" | cut -f1) saved in $OUTPUT_FILE"