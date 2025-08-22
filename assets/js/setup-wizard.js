/**
 * WCEventsFP Setup Wizard JavaScript
 * 
 * @package WCEventsFP
 * @version 2.1.0
 */

/**
 * Setup Wizard Functionality
 */
class WCEFPSetupWizard {
    constructor() {
        this.bindEvents();
    }

    /**
     * Bind event listeners
     */
    bindEvents() {
        document.addEventListener('DOMContentLoaded', () => {
            this.initFeatureSelection();
        });
    }

    /**
     * Initialize feature selection handling
     */
    initFeatureSelection() {
        const featureCheckboxes = document.querySelectorAll('input[name="features[]"]');
        featureCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                const option = e.target.closest('.wcefp-feature-option');
                if (e.target.checked) {
                    option.classList.add('enabled');
                } else {
                    option.classList.remove('enabled');
                }
            });
        });
    }

    /**
     * Start activation process with progress indication
     * @param {Array} steps - Array of step descriptions
     */
    startActivation(steps = []) {
        const defaultSteps = [
            'Checking environment...',
            'Loading core system...',
            'Initializing database...',
            'Setting up features...',
            'Finalizing configuration...',
            'Activation complete!'
        ];

        const activationSteps = steps.length > 0 ? steps : defaultSteps;
        let currentStep = 0;
        
        const progressFill = document.getElementById('progress-fill');
        const statusElement = document.getElementById('activation-status');

        const updateProgress = () => {
            if (currentStep < activationSteps.length - 1) {
                if (statusElement) {
                    statusElement.textContent = activationSteps[currentStep];
                }
                if (progressFill) {
                    progressFill.style.width = ((currentStep + 1) / activationSteps.length * 100) + '%';
                }
                currentStep++;
                setTimeout(updateProgress, 1500);
            } else {
                // Final step
                if (statusElement) {
                    statusElement.textContent = activationSteps[currentStep];
                }
                if (progressFill) {
                    progressFill.style.width = '100%';
                    progressFill.classList.add('success');
                }
                
                // Show completion
                setTimeout(() => {
                    this.showActivationComplete();
                }, 1000);
            }
        };

        setTimeout(updateProgress, 500);
    }

    /**
     * Show activation complete state
     */
    showActivationComplete() {
        const resultsElement = document.getElementById('activation-results');
        const actionsElement = document.getElementById('activation-actions');
        
        if (resultsElement) {
            resultsElement.style.display = 'block';
            resultsElement.innerHTML = '<div class="wcefp-test-result success"><span>✅ Plugin activated successfully!</span></div>';
        }
        
        if (actionsElement) {
            actionsElement.style.display = 'block';
        }
    }

    /**
     * Show loading state for elements
     * @param {Element} element 
     */
    showLoading(element) {
        if (element) {
            element.classList.add('loading');
        }
    }

    /**
     * Hide loading state for elements
     * @param {Element} element 
     */
    hideLoading(element) {
        if (element) {
            element.classList.remove('loading');
        }
    }

    /**
     * Display error message
     * @param {string} message 
     */
    showError(message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'wcefp-test-result error';
        errorDiv.innerHTML = `<span>❌ ${message}</span>`;
        
        const container = document.querySelector('.wcefp-step-content');
        if (container) {
            container.insertBefore(errorDiv, container.firstChild);
        }
    }

    /**
     * Display success message
     * @param {string} message 
     */
    showSuccess(message) {
        const successDiv = document.createElement('div');
        successDiv.className = 'wcefp-test-result success';
        successDiv.innerHTML = `<span>✅ ${message}</span>`;
        
        const container = document.querySelector('.wcefp-step-content');
        if (container) {
            container.insertBefore(successDiv, container.firstChild);
        }
    }
}

// Initialize wizard when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.wcefpWizard = new WCEFPSetupWizard();
});

// Global function for backwards compatibility
function wcefpStartActivation(steps) {
    if (window.wcefpWizard) {
        window.wcefpWizard.startActivation(steps);
    }
}