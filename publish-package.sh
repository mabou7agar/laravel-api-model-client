#!/bin/bash

# Laravel API Model Client - Automated Commit and Publish Script
# This script automates the process of committing changes, creating tags, and publishing to Packagist

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PACKAGE_NAME="m-tech-stack/laravel-api-model-client"
REPO_URL="git@github.com:mabou7agar/laravel-api-model-client.git"
COMPOSER_FILE="composer.json"

# Functions
print_header() {
    echo -e "${BLUE}================================================${NC}"
    echo -e "${BLUE}  Laravel API Model Client - Publish Script${NC}"
    echo -e "${BLUE}================================================${NC}"
    echo ""
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

# Get current version from composer.json
get_current_version() {
    if [ -f "$COMPOSER_FILE" ]; then
        grep -o '"version": *"[^"]*"' "$COMPOSER_FILE" | grep -o '[0-9]\+\.[0-9]\+\.[0-9]\+'
    else
        echo "0.0.0"
    fi
}

# Increment version (patch by default)
increment_version() {
    local version=$1
    local type=${2:-patch}
    
    IFS='.' read -ra VERSION_PARTS <<< "$version"
    local major=${VERSION_PARTS[0]}
    local minor=${VERSION_PARTS[1]}
    local patch=${VERSION_PARTS[2]}
    
    case $type in
        major)
            major=$((major + 1))
            minor=0
            patch=0
            ;;
        minor)
            minor=$((minor + 1))
            patch=0
            ;;
        patch)
            patch=$((patch + 1))
            ;;
    esac
    
    echo "$major.$minor.$patch"
}

# Update version in composer.json
update_composer_version() {
    local new_version=$1
    if [ -f "$COMPOSER_FILE" ]; then
        # Use sed to update the version in composer.json
        if [[ "$OSTYPE" == "darwin"* ]]; then
            # macOS
            sed -i '' "s/\"version\": *\"[^\"]*\"/\"version\": \"$new_version\"/" "$COMPOSER_FILE"
        else
            # Linux
            sed -i "s/\"version\": *\"[^\"]*\"/\"version\": \"$new_version\"/" "$COMPOSER_FILE"
        fi
        print_success "Updated composer.json version to $new_version"
    else
        print_error "composer.json not found!"
        exit 1
    fi
}

# Validate package
validate_package() {
    print_info "Running package validation..."
    if [ -f "validate-package.php" ]; then
        php validate-package.php
        local exit_code=$?
        if [ $exit_code -eq 0 ]; then
            print_success "Package validation passed"
        else
            print_warning "Package validation has warnings (exit code: $exit_code)"
            echo "Continue anyway? (y/N)"
            read -r response
            if [[ ! "$response" =~ ^[Yy]$ ]]; then
                print_error "Aborted by user"
                exit 1
            fi
        fi
    else
        print_warning "validate-package.php not found, skipping validation"
    fi
}

# Run tests
run_tests() {
    print_info "Running tests..."
    if [ -f "vendor/bin/phpunit" ]; then
        php -d memory_limit=512M vendor/bin/phpunit --stop-on-failure --testdox
        if [ $? -eq 0 ]; then
            print_success "Tests passed"
        else
            print_warning "Some tests failed"
            echo "Continue anyway? (y/N)"
            read -r response
            if [[ ! "$response" =~ ^[Yy]$ ]]; then
                print_error "Aborted due to test failures"
                exit 1
            fi
        fi
    else
        print_warning "PHPUnit not found, skipping tests"
    fi
}

# Git operations
git_operations() {
    local version=$1
    local commit_message=$2
    
    print_info "Performing git operations..."
    
    # Check if there are changes to commit
    if ! git diff --quiet || ! git diff --cached --quiet; then
        print_info "Adding changes to git..."
        git add .
        
        print_info "Committing changes..."
        git commit -m "$commit_message"
        print_success "Changes committed"
    else
        print_info "No changes to commit"
    fi
    
    # Create and push tag
    print_info "Creating tag v$version..."
    if git tag -a "v$version" -m "Release v$version"; then
        print_success "Tag v$version created"
    else
        print_warning "Tag v$version might already exist"
    fi
    
    # Push to remote
    print_info "Pushing to remote repository..."
    git push origin master
    git push origin --tags
    print_success "Pushed to remote repository"
}

# Main script
main() {
    print_header
    
    # Check if we're in the right directory
    if [ ! -f "$COMPOSER_FILE" ]; then
        print_error "composer.json not found. Are you in the package directory?"
        exit 1
    fi
    
    # Get current version
    current_version=$(get_current_version)
    print_info "Current version: $current_version"
    
    # Ask for version increment type
    echo "Select version increment type:"
    echo "1) Patch (${current_version} -> $(increment_version $current_version patch))"
    echo "2) Minor (${current_version} -> $(increment_version $current_version minor))"
    echo "3) Major (${current_version} -> $(increment_version $current_version major))"
    echo "4) Custom version"
    echo "5) Skip version update"
    
    read -p "Enter choice (1-5): " choice
    
    case $choice in
        1)
            new_version=$(increment_version $current_version patch)
            ;;
        2)
            new_version=$(increment_version $current_version minor)
            ;;
        3)
            new_version=$(increment_version $current_version major)
            ;;
        4)
            read -p "Enter custom version: " new_version
            ;;
        5)
            new_version=$current_version
            print_info "Skipping version update"
            ;;
        *)
            print_error "Invalid choice"
            exit 1
            ;;
    esac
    
    # Update composer.json if version changed
    if [ "$new_version" != "$current_version" ]; then
        update_composer_version $new_version
    fi
    
    # Get commit message
    read -p "Enter commit message (or press Enter for default): " commit_message
    if [ -z "$commit_message" ]; then
        if [ "$new_version" != "$current_version" ]; then
            commit_message="Release v$new_version"
        else
            commit_message="Update package files"
        fi
    fi
    
    # Validate package
    validate_package
    
    # Run tests (optional)
    echo "Run tests before publishing? (Y/n)"
    read -r run_tests_choice
    if [[ ! "$run_tests_choice" =~ ^[Nn]$ ]]; then
        run_tests
    fi
    
    # Perform git operations
    git_operations $new_version "$commit_message"
    
    # Success message
    echo ""
    print_success "Package published successfully!"
    print_info "Package: $PACKAGE_NAME"
    print_info "Version: v$new_version"
    print_info "Repository: $REPO_URL"
    echo ""
    print_info "Next steps:"
    echo "  1. Check Packagist.org for automatic updates"
    echo "  2. If not automatic, manually update at: https://packagist.org/packages/$PACKAGE_NAME"
    echo "  3. Verify the new version is available via: composer show $PACKAGE_NAME"
    echo ""
}

# Run main function
main "$@"
