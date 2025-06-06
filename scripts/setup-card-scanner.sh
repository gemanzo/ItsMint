#!/bin/bash

# ItsMint Card Scanner Setup Script
# This script sets up the OCR card scanning functionality

set -e

echo "🚀 Setting up ItsMint Card Scanner..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root for system packages
check_root() {
    if [ "$EUID" -eq 0 ]; then
        print_warning "Running as root. Some commands may need sudo."
    fi
}

# Detect OS
detect_os() {
    if [[ "$OSTYPE" == "linux-gnu"* ]]; then
        # Linux
        if command -v apt-get &> /dev/null; then
            OS="ubuntu"
        elif command -v yum &> /dev/null; then
            OS="rhel"
        else
            OS="linux"
        fi
    elif [[ "$OSTYPE" == "darwin"* ]]; then
        # Mac OSX
        OS="macos"
    else
        OS="unknown"
    fi
    
    print_status "Detected OS: $OS"
}

# Install Tesseract OCR
install_tesseract() {
    print_status "Installing Tesseract OCR..."
    
    case $OS in
        "ubuntu")
            print_status "Installing Tesseract on Ubuntu/Debian..."
            if [ "$EUID" -eq 0 ]; then
                apt-get update
                apt-get install -y tesseract-ocr tesseract-ocr-eng tesseract-ocr-jpn tesseract-ocr-ita
            else
                sudo apt-get update
                sudo apt-get install -y tesseract-ocr tesseract-ocr-eng tesseract-ocr-jpn tesseract-ocr-ita
            fi
            ;;
        "rhel")
            print_status "Installing Tesseract on RHEL/CentOS..."
            if [ "$EUID" -eq 0 ]; then
                yum install -y tesseract tesseract-langpack-eng tesseract-langpack-jpn tesseract-langpack-ita
            else
                sudo yum install -y tesseract tesseract-langpack-eng tesseract-langpack-jpn tesseract-langpack-ita
            fi
            ;;
        "macos")
            print_status "Installing Tesseract on macOS..."
            if command -v brew &> /dev/null; then
                brew install tesseract tesseract-lang
            else
                print_error "Homebrew not found. Please install Homebrew first: https://brew.sh/"
                exit 1
            fi
            ;;
        *)
            print_error "Unsupported OS. Please install Tesseract manually."
            print_status "Visit: https://tesseract-ocr.github.io/tessdoc/Installation.html"
            exit 1
            ;;
    esac
    
    # Verify installation
    if command -v tesseract &> /dev/null; then
        TESSERACT_VERSION=$(tesseract --version | head -n1)
        print_success "Tesseract installed: $TESSERACT_VERSION"
    else
        print_error "Tesseract installation failed"
        exit 1
    fi
}

# Install PHP dependencies
install_php_dependencies() {
    print_status "Installing PHP dependencies..."
    
    if ! command -v composer &> /dev/null; then
        print_error "Composer not found. Please install Composer first."
        exit 1
    fi
    
    composer require thiagoalessio/tesseract_ocr intervention/image
    print_success "PHP dependencies installed"
}

# Install Node.js dependencies
install_node_dependencies() {
    print_status "Installing Node.js dependencies..."
    
    if ! command -v npm &> /dev/null; then
        print_error "npm not found. Please install Node.js first."
        exit 1
    fi
    
    npm install @heroicons/vue
    print_success "Node.js dependencies installed"
}

# Create necessary directories
create_directories() {
    print_status "Creating necessary directories..."
    
    mkdir -p storage/app/public/card-scans
    mkdir -p storage/logs
    
    # Set proper permissions
    chmod -R 755 storage/app/public/card-scans
    chmod -R 755 storage/logs
    
    print_success "Directories created with proper permissions"
}

# Run database migrations
run_migrations() {
    print_status "Running database migrations..."
    
    if ! command -v php &> /dev/null; then
        print_error "PHP not found. Please install PHP first."
        exit 1
    fi
    
    # Check if Laravel is properly configured
    if [ ! -f ".env" ]; then
        print_error ".env file not found. Please configure your Laravel environment first."
        exit 1
    fi
    
    php artisan migrate --force
    print_success "Database migrations completed"
}

# Create symbolic link for storage
create_storage_link() {
    print_status "Creating storage symbolic link..."
    
    php artisan storage:link
    print_success "Storage link created"
}

# Test OCR functionality
test_ocr() {
    print_status "Testing OCR functionality..."
    
    # Create a simple test script
    cat > test_ocr.php << 'EOF'
<?php
require_once 'vendor/autoload.php';

use thiagoalessio\TesseractOCR\TesseractOCR;

try {
    // Create a simple test image with text
    $image = imagecreate(200, 50);
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    imagestring($image, 5, 10, 15, 'TEST CARD', $black);
    imagepng($image, 'test_image.png');
    imagedestroy($image);
    
    // Test OCR
    $ocr = new TesseractOCR('test_image.png');
    $text = $ocr->run();
    
    echo "OCR Result: " . trim($text) . "\n";
    
    // Cleanup
    unlink('test_image.png');
    
    if (strpos(strtoupper($text), 'TEST') !== false) {
        echo "✅ OCR test passed!\n";
        exit(0);
    } else {
        echo "❌ OCR test failed - unexpected result\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "❌ OCR test failed: " . $e->getMessage() . "\n";
    exit(1);
}
EOF

    php test_ocr.php
    rm -f test_ocr.php
    
    if [ $? -eq 0 ]; then
        print_success "OCR test passed"
    else
        print_error "OCR test failed"
        exit 1
    fi
}

# Configure environment variables
configure_env() {
    print_status "Checking environment configuration..."
    
    # Check if required variables are set
    if ! grep -q "TESSERACT_OCR_PATH" .env; then
        print_status "Adding TESSERACT_OCR_PATH to .env..."
        echo "" >> .env
        echo "# Card Scanner Configuration" >> .env
        echo "TESSERACT_OCR_PATH=$(which tesseract)" >> .env
    fi
    
    print_success "Environment configuration completed"
}

# Main installation process
main() {
    echo "==========================================="
    echo "     ItsMint Card Scanner Setup Script    "
    echo "==========================================="
    echo ""
    
    check_root
    detect_os
    
    print_status "Starting installation process..."
    
    # Install system dependencies
    install_tesseract
    
    # Install application dependencies
    install_php_dependencies
    install_node_dependencies
    
    # Setup Laravel
    create_directories
    create_storage_link
    configure_env
    
    # Run migrations (optional - user might want to do this manually)
    read -p "Do you want to run database migrations now? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        run_migrations
    else
        print_warning "Skipping database migrations. Run 'php artisan migrate' manually when ready."
    fi
    
    # Test OCR
    test_ocr
    
    echo ""
    print_success "Card Scanner setup completed successfully! 🎉"
    echo ""
    echo "Next steps:"
    echo "1. Make sure your web server has write permissions to storage/ directory"
    echo "2. Configure your .env file with proper database settings"
    echo "3. Run 'php artisan migrate' if you haven't already"
    echo "4. Run 'npm run build' to compile frontend assets"
    echo "5. Access the card scanner at /card-scan in your application"
    echo ""
    echo "For troubleshooting, check docs/CARD_SCANNER.md"
}

# Run main function
main "$@" 