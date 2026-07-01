/**
 * ADV Clarity PWA Screenshot Generator
 * 
 * This script helps generate screenshots for the PWA manifest.
 * It can be run in a browser environment to capture screenshots
 * at the exact dimensions required for the manifest.
 */

class PWAScreenshotGenerator {
    constructor() {
        this.screenshots = [
            {
                name: 'desktop-dashboard-wide',
                url: '/dashboard.php',
                width: 1280,
                height: 720,
                formFactor: 'wide',
                description: 'Desktop dashboard view with statistics and charts'
            },
            {
                name: 'desktop-inventory-wide',
                url: '/inventory/',
                width: 1280,
                height: 720,
                formFactor: 'wide',
                description: 'Desktop inventory management interface'
            },
            {
                name: 'mobile-dashboard-narrow',
                url: '/dashboard.php',
                width: 390,
                height: 844,
                formFactor: 'narrow',
                description: 'Mobile dashboard view with responsive layout'
            },
            {
                name: 'mobile-installation-narrow',
                url: '/installation/',
                width: 390,
                height: 844,
                formFactor: 'narrow',
                description: 'Mobile installation tracking interface'
            }
        ];
    }

    /**
     * Generate all screenshots
     */
    async generateAll() {
        console.log('Starting PWA screenshot generation...');
        
        for (const screenshot of this.screenshots) {
            await this.generateScreenshot(screenshot);
        }
        
        console.log('Screenshot generation complete!');
    }

    /**
     * Generate a single screenshot
     */
    async generateScreenshot(config) {
        console.log(`Generating ${config.name}...`);
        
        try {
            // Set viewport size
            await this.setViewportSize(config.width, config.height);
            
            // Navigate to URL
            if (window.location.pathname !== config.url) {
                window.location.href = config.url;
                await this.waitForPageLoad();
            }
            
            // Wait for content to load
            await this.waitForContent();
            
            // Capture screenshot
            await this.captureScreenshot(config);
            
            console.log(`✓ Generated ${config.name}`);
        } catch (error) {
            console.error(`✗ Failed to generate ${config.name}:`, error);
        }
    }

    /**
     * Set viewport size (requires browser dev tools or extension)
     */
    async setViewportSize(width, height) {
        // This would typically be done through browser dev tools
        // or a browser automation tool like Puppeteer
        console.log(`Setting viewport to ${width}x${height}`);
        
        // For manual capture, log instructions
        console.log(`📱 Please set your browser viewport to ${width}x${height} pixels`);
        console.log(`   - Open Developer Tools (F12)`);
        console.log(`   - Click the device toolbar icon (Ctrl+Shift+M)`);
        console.log(`   - Set custom dimensions: ${width} x ${height}`);
    }

    /**
     * Wait for page to load
     */
    async waitForPageLoad() {
        return new Promise((resolve) => {
            if (document.readyState === 'complete') {
                resolve();
            } else {
                window.addEventListener('load', resolve);
            }
        });
    }

    /**
     * Wait for content to load (charts, images, etc.)
     */
    async waitForContent() {
        // Wait for charts to render
        await this.waitForCharts();
        
        // Wait for images to load
        await this.waitForImages();
        
        // Wait for any animations to complete
        await new Promise(resolve => setTimeout(resolve, 1000));
    }

    /**
     * Wait for Chart.js charts to render
     */
    async waitForCharts() {
        return new Promise((resolve) => {
            const checkCharts = () => {
                const charts = document.querySelectorAll('canvas');
                let allLoaded = true;
                
                charts.forEach(canvas => {
                    const ctx = canvas.getContext('2d');
                    if (!ctx || !window.Chart) {
                        allLoaded = false;
                    }
                });
                
                if (allLoaded || Date.now() - startTime > 5000) {
                    resolve();
                } else {
                    setTimeout(checkCharts, 100);
                }
            };
            
            const startTime = Date.now();
            checkCharts();
        });
    }

    /**
     * Wait for images to load
     */
    async waitForImages() {
        const images = document.querySelectorAll('img');
        const promises = Array.from(images).map(img => {
            return new Promise((resolve) => {
                if (img.complete) {
                    resolve();
                } else {
                    img.addEventListener('load', resolve);
                    img.addEventListener('error', resolve);
                }
            });
        });
        
        await Promise.all(promises);
    }

    /**
     * Capture screenshot (manual instructions)
     */
    async captureScreenshot(config) {
        console.log(`📸 Ready to capture ${config.name}`);
        console.log(`   Description: ${config.description}`);
        console.log(`   Dimensions: ${config.width}x${config.height}`);
        console.log(`   Form Factor: ${config.formFactor}`);
        console.log(`   Save as: assets/screenshots/${config.name}.png`);
        console.log('');
        console.log('Manual capture instructions:');
        console.log('1. Ensure viewport is set to correct dimensions');
        console.log('2. Take a screenshot of the visible area');
        console.log('3. Save as PNG with exact filename above');
        console.log('4. Optimize file size (target < 1MB)');
        console.log('');
        
        // For automated capture, this would use browser APIs
        // or tools like html2canvas, but those have limitations
        // with complex layouts and charts
    }

    /**
     * Validate manifest screenshots
     */
    validateManifest() {
        console.log('Validating manifest screenshots...');
        
        fetch('/app.webmanifest')
            .then(response => response.json())
            .then(manifest => {
                const screenshots = manifest.screenshots || [];
                
                screenshots.forEach(screenshot => {
                    console.log(`Checking ${screenshot.src}...`);
                    
                    const img = new Image();
                    img.onload = () => {
                        const expectedWidth = parseInt(screenshot.sizes.split('x')[0]);
                        const expectedHeight = parseInt(screenshot.sizes.split('x')[1]);
                        
                        if (img.width === expectedWidth && img.height === expectedHeight) {
                            console.log(`✓ ${screenshot.src} - Correct dimensions`);
                        } else {
                            console.log(`✗ ${screenshot.src} - Wrong dimensions: ${img.width}x${img.height}, expected: ${expectedWidth}x${expectedHeight}`);
                        }
                    };
                    img.onerror = () => {
                        console.log(`✗ ${screenshot.src} - File not found or invalid`);
                    };
                    img.src = screenshot.src;
                });
            })
            .catch(error => {
                console.error('Failed to load manifest:', error);
            });
    }

    /**
     * Show generation instructions
     */
    showInstructions() {
        console.log('PWA Screenshot Generation Instructions');
        console.log('=====================================');
        console.log('');
        console.log('This tool helps generate screenshots for the PWA manifest.');
        console.log('');
        console.log('Available methods:');
        console.log('- generator.generateAll() - Generate all screenshots');
        console.log('- generator.validateManifest() - Validate existing screenshots');
        console.log('- generator.showInstructions() - Show this help');
        console.log('');
        console.log('For automated screenshot generation, consider using:');
        console.log('- Puppeteer (Node.js)');
        console.log('- Playwright (Node.js)');
        console.log('- Browser extensions');
        console.log('- Online screenshot tools');
        console.log('');
        console.log('Manual capture workflow:');
        console.log('1. Open browser developer tools');
        console.log('2. Enable device simulation');
        console.log('3. Set custom viewport dimensions');
        console.log('4. Navigate to each URL');
        console.log('5. Capture and save screenshots');
        console.log('6. Optimize file sizes');
    }
}

// Initialize generator
const generator = new PWAScreenshotGenerator();

// Show instructions on load
generator.showInstructions();

// Make generator available globally
window.pwaScreenshotGenerator = generator;

console.log('PWA Screenshot Generator loaded. Use window.pwaScreenshotGenerator to access methods.');