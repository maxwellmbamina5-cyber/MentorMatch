// assets/js/app.js - General UI Enhancements

document.addEventListener('DOMContentLoaded', function() {
    
    // ============================================
    // AUTO-DISMISS ALERTS
    // ============================================
    document.querySelectorAll('.alert:not(.alert-permanent)').forEach(function(alert) {
        setTimeout(function() {
            if (alert) {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                bsAlert.close();
            }
        }, 5000);
    });
    
    // ============================================
    // CONFIRM DIALOGS (Mobile-friendly)
    // ============================================
    document.querySelectorAll('[data-confirm]').forEach(function(element) {
        element.addEventListener('click', function(e) {
            const message = this.dataset.confirm || 'Are you sure?';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    // ============================================
    // SEARCH INPUT CLEAR BUTTON
    // ============================================
    document.querySelectorAll('input[type="search"]').forEach(function(input) {
        const wrapper = document.createElement('div');
        wrapper.style.position = 'relative';
        wrapper.style.display = 'inline-block';
        wrapper.style.width = '100%';
        
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        
        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'btn btn-link position-absolute';
        clearBtn.style.cssText = `
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            display: none;
            padding: 4px 8px;
            min-height: 30px;
            min-width: 30px;
            border: none;
            background: none;
        `;
        clearBtn.innerHTML = '<i class="bi bi-x-circle"></i>';
        wrapper.appendChild(clearBtn);
        
        input.addEventListener('input', function() {
            if (this.value.length > 0) {
                clearBtn.style.display = 'block';
            } else {
                clearBtn.style.display = 'none';
            }
        });
        
        clearBtn.addEventListener('click', function() {
            input.value = '';
            input.dispatchEvent(new Event('input'));
            input.focus();
        });
    });
    
    // ============================================
    // SMOOTH SCROLL ANCHOR LINKS
    // ============================================
    document.querySelectorAll('a[href^="#"]:not([href="#"])').forEach(function(anchor) {
        anchor.addEventListener('click', function(e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // ============================================
    // COPY TO CLIPBOARD
    // ============================================
    document.querySelectorAll('[data-copy]').forEach(function(element) {
        element.addEventListener('click', function() {
            const text = this.dataset.copy || this.textContent;
            navigator.clipboard.writeText(text).then(function() {
                showToast('Copied to clipboard!', 'success');
            }).catch(function() {
                // Fallback
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                showToast('Copied to clipboard!', 'success');
            });
        });
    });
    
    // ============================================
    // LOADING INDICATOR FOR AJAX REQUESTS
    // ============================================
    document.addEventListener('ajaxStart', function() {
        const overlay = document.createElement('div');
        overlay.id = 'ajaxLoader';
        overlay.className = 'spinner-overlay';
        overlay.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status" style="width: 48px; height: 48px;">
                    <span class="sr-only">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading...</p>
            </div>
        `;
        document.body.appendChild(overlay);
    });
    
    document.addEventListener('ajaxEnd', function() {
        const overlay = document.getElementById('ajaxLoader');
        if (overlay) {
            overlay.remove();
        }
    });
    
    // ============================================
    // BETTER FORM VALIDATION FEEDBACK
    // ============================================
    document.querySelectorAll('.form-control, .form-select').forEach(function(input) {
        input.addEventListener('blur', function() {
            if (this.hasAttribute('required') && !this.value.trim()) {
                this.classList.add('is-invalid');
            } else if (this.hasAttribute('required') && this.value.trim()) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            }
        });
        
        input.addEventListener('input', function() {
            if (this.classList.contains('is-invalid') && this.value.trim()) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            }
        });
    });
    
    // ============================================
    // KEYBOARD SHORTCUTS (Desktop)
    // ============================================
    document.addEventListener('keydown', function(e) {
        // Ctrl + / to focus search
        if (e.ctrlKey && e.key === '/') {
            e.preventDefault();
            const searchInput = document.querySelector('input[type="search"]');
            if (searchInput) {
                searchInput.focus();
            }
        }
        
        // Escape to close modals
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.show').forEach(function(modal) {
                const bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) {
                    bsModal.hide();
                }
            });
        }
    });
    
});