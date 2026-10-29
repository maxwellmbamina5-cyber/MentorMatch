// assets/js/mobile.js - Mobile UX Enhancements

document.addEventListener('DOMContentLoaded', function() {
    
    // ============================================
    // PULL TO REFRESH
    // ============================================
    let touchStartY = 0;
    let touchEndY = 0;
    let isRefreshing = false;
    const pullThreshold = 100;
    
    document.addEventListener('touchstart', function(e) {
        touchStartY = e.touches[0].clientY;
    }, { passive: true });
    
    document.addEventListener('touchmove', function(e) {
        touchEndY = e.touches[0].clientY;
        const pullDistance = touchEndY - touchStartY;
        
        if (pullDistance > pullThreshold && window.scrollY === 0 && !isRefreshing) {
            // Show pull to refresh indicator
            showPullToRefresh();
        }
    }, { passive: true });
    
    document.addEventListener('touchend', function(e) {
        const pullDistance = touchEndY - touchStartY;
        
        if (pullDistance > pullThreshold && window.scrollY === 0 && !isRefreshing) {
            // Perform refresh
            performRefresh();
        }
        
        hidePullToRefresh();
    }, { passive: true });
    
    function showPullToRefresh() {
        let indicator = document.getElementById('pullToRefresh');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'pullToRefresh';
            indicator.className = 'pull-to-refresh';
            indicator.innerHTML = '<i class="bi bi-arrow-down"></i> Pull to refresh';
            document.body.prepend(indicator);
        }
        indicator.style.display = 'block';
        indicator.innerHTML = '<i class="bi bi-arrow-down"></i> Release to refresh';
    }
    
    function hidePullToRefresh() {
        const indicator = document.getElementById('pullToRefresh');
        if (indicator) {
            indicator.style.display = 'none';
        }
    }
    
    function performRefresh() {
        isRefreshing = true;
        const indicator = document.getElementById('pullToRefresh');
        if (indicator) {
            indicator.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"></div> Refreshing...';
        }
        
        setTimeout(function() {
            location.reload();
        }, 1000);
    }
    
    // ============================================
    // BOTTOM SHEET (Mobile Modal Alternative)
    // ============================================
    window.openBottomSheet = function(content, title) {
        // Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'bottom-sheet-overlay';
        overlay.id = 'bottomSheetOverlay';
        document.body.appendChild(overlay);
        
        // Create bottom sheet
        const sheet = document.createElement('div');
        sheet.className = 'bottom-sheet';
        sheet.id = 'bottomSheet';
        sheet.innerHTML = `
            <div class="sheet-handle"></div>
            <div class="sheet-header">${title || 'Details'}</div>
            <div class="sheet-body">${content}</div>
        `;
        document.body.appendChild(sheet);
        
        // Show
        setTimeout(function() {
            sheet.classList.add('show');
            overlay.classList.add('show');
        }, 10);
        
        // Close on overlay click
        overlay.addEventListener('click', function() {
            closeBottomSheet();
        });
        
        // Close on swipe down
        let startY = 0;
        sheet.addEventListener('touchstart', function(e) {
            startY = e.touches[0].clientY;
        }, { passive: true });
        
        sheet.addEventListener('touchmove', function(e) {
            const currentY = e.touches[0].clientY;
            const diff = currentY - startY;
            if (diff > 50 && sheet.scrollTop === 0) {
                closeBottomSheet();
            }
        }, { passive: true });
    };
    
    window.closeBottomSheet = function() {
        const sheet = document.getElementById('bottomSheet');
        const overlay = document.getElementById('bottomSheetOverlay');
        if (sheet) {
            sheet.classList.remove('show');
            setTimeout(function() {
                if (sheet.parentNode) sheet.parentNode.removeChild(sheet);
            }, 400);
        }
        if (overlay) {
            overlay.classList.remove('show');
            setTimeout(function() {
                if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
            }, 400);
        }
    };
    
    // ============================================
    // TOAST NOTIFICATIONS
    // ============================================
    window.showToast = function(message, type = 'info', duration = 3000) {
        const container = document.querySelector('.toast-container');
        if (!container) return;
        
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'danger' ? 'danger' : type === 'warning' ? 'warning text-dark' : 'primary'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.style.borderRadius = '12px';
        
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        container.appendChild(toast);
        
        const bsToast = new bootstrap.Toast(toast, {
            animation: true,
            autohide: true,
            delay: duration
        });
        bsToast.show();
        
        toast.addEventListener('hidden.bs.toast', function() {
            if (this.parentNode) this.parentNode.removeChild(this);
        });
    };
    
    // ============================================
    // SWIPE TO DELETE (For lists)
    // ============================================
    function setupSwipeToDelete(selector) {
        document.querySelectorAll(selector).forEach(function(element) {
            let startX = 0;
            let currentX = 0;
            let isSwiping = false;
            
            element.addEventListener('touchstart', function(e) {
                startX = e.touches[0].clientX;
                isSwiping = true;
            }, { passive: true });
            
            element.addEventListener('touchmove', function(e) {
                if (!isSwiping) return;
                currentX = e.touches[0].clientX;
                const diff = startX - currentX;
                
                if (diff > 50) {
                    element.style.transform = `translateX(-80px)`;
                    element.style.transition = 'transform 0.3s ease';
                } else if (diff < -50) {
                    element.style.transform = `translateX(0px)`;
                }
            }, { passive: true });
            
            element.addEventListener('touchend', function(e) {
                isSwiping = false;
                const diff = startX - currentX;
                if (diff > 50) {
                    // Show delete button
                    element.style.transform = `translateX(-80px)`;
                } else {
                    element.style.transform = `translateX(0px)`;
                }
            }, { passive: true });
        });
    }
    
    // Initialize swipe to delete for list items
    // setupSwipeToDelete('.list-group-item');
    
    // ============================================
    // SCROLL TO TOP BUTTON
    // ============================================
    const scrollBtn = document.createElement('button');
    scrollBtn.id = 'scrollTopBtn';
    scrollBtn.className = 'btn btn-primary rounded-circle position-fixed';
    scrollBtn.style.cssText = `
        bottom: 80px;
        right: 16px;
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: none;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
        z-index: 999;
        border: none;
    `;
    scrollBtn.innerHTML = '<i class="bi bi-chevron-up" style="font-size: 1.5rem;"></i>';
    document.body.appendChild(scrollBtn);
    
    window.addEventListener('scroll', function() {
        if (window.scrollY > 300) {
            scrollBtn.style.display = 'flex';
        } else {
            scrollBtn.style.display = 'none';
        }
    });
    
    scrollBtn.addEventListener('click', function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    
    // ============================================
    // FORM SUBMISSION INDICATOR
    // ============================================
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Processing...';
            }
        });
    });
    
    // ============================================
    // RESPONSIVE TABLE WRAPPER
    // ============================================
    document.querySelectorAll('.table-responsive').forEach(function(table) {
        // Add scroll hint for mobile
        if (table.scrollWidth > table.clientWidth) {
            const hint = document.createElement('div');
            hint.className = 'text-muted small text-center py-1';
            hint.innerHTML = '<i class="bi bi-chevron-right"></i> Swipe to see more';
            table.parentNode.insertBefore(hint, table);
            
            setTimeout(function() {
                hint.style.display = 'none';
            }, 3000);
        }
    });
    
    // ============================================
    // TOUCH-FRIENDLY MODAL CLOSE
    // ============================================
    document.querySelectorAll('.modal').forEach(function(modal) {
        modal.addEventListener('shown.bs.modal', function() {
            // Focus on first input if any
            const firstInput = this.querySelector('input, textarea, select');
            if (firstInput) {
                setTimeout(function() {
                    firstInput.focus();
                }, 300);
            }
        });
    });
    
    // ============================================
    // BETTER DROPDOWN ON MOBILE
    // ============================================
    document.querySelectorAll('.dropdown-toggle').forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            if (window.innerWidth < 768) {
                e.preventDefault();
                const dropdown = this.closest('.dropdown');
                const menu = dropdown.querySelector('.dropdown-menu');
                if (menu) {
                    const isOpen = menu.classList.contains('show');
                    menu.classList.toggle('show');
                    dropdown.querySelector('.dropdown-toggle').setAttribute('aria-expanded', !isOpen);
                }
            }
        });
    });
    
    // ============================================
    // CONSOLE LOG FOR MOBILE DEBUGGING
    // ============================================
    console.log('📱 MentorMatch Mobile UI Loaded');
    console.log('📱 Screen Width:', window.innerWidth);
    console.log('📱 Screen Height:', window.innerHeight);
    console.log('📱 User Agent:', navigator.userAgent);
    
});