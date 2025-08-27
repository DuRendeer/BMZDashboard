class AnimatedList {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            items: options.items || [],
            onItemSelect: options.onItemSelect || null,
            showGradients: options.showGradients !== false,
            enableArrowNavigation: options.enableArrowNavigation !== false,
            displayScrollbar: options.displayScrollbar !== false,
            initialSelectedIndex: options.initialSelectedIndex || -1,
            className: options.className || '',
            itemClassName: options.itemClassName || '',
            ...options
        };
        
        this.selectedIndex = this.options.initialSelectedIndex;
        this.keyboardNav = false;
        this.topGradientOpacity = 0;
        this.bottomGradientOpacity = 1;
        
        this.init();
    }
    
    init() {
        this.createStructure();
        this.bindEvents();
        this.updateGradients();
    }
    
    createStructure() {
        this.container.className = `scroll-list-container ${this.options.className}`;
        this.container.innerHTML = `
            <div class="scroll-list ${!this.options.displayScrollbar ? 'no-scrollbar' : ''}">
                ${this.options.items.map((item, index) => this.createItemHTML(item, index)).join('')}
            </div>
            ${this.options.showGradients ? `
                <div class="top-gradient" style="opacity: ${this.topGradientOpacity}"></div>
                <div class="bottom-gradient" style="opacity: ${this.bottomGradientOpacity}"></div>
            ` : ''}
        `;
        
        this.listElement = this.container.querySelector('.scroll-list');
        this.topGradient = this.container.querySelector('.top-gradient');
        this.bottomGradient = this.container.querySelector('.bottom-gradient');
    }
    
    createItemHTML(item, index) {
        const isSelected = this.selectedIndex === index;
        const itemContent = typeof item === 'object' ? 
            `<div class="item-content">
                ${item.avatar ? `<div class="item-avatar">${item.avatar}</div>` : ''}
                <div class="item-text">
                    <div class="item-title">${item.name || item.title || item}</div>
                    ${item.subtitle ? `<div class="item-subtitle">${item.subtitle}</div>` : ''}
                    ${item.badge ? `<div class="item-badge ${item.badgeClass || ''}">${item.badge}</div>` : ''}
                </div>
                ${item.action ? `<div class="item-action">${item.action}</div>` : ''}
            </div>` :
            `<p class="item-text">${item}</p>`;
            
        return `
            <div class="animated-item" data-index="${index}" style="animation-delay: ${index * 0.1}s">
                <div class="item ${isSelected ? 'selected' : ''} ${this.options.itemClassName}">
                    ${itemContent}
                </div>
            </div>
        `;
    }
    
    bindEvents() {
        // Scroll event
        this.listElement.addEventListener('scroll', (e) => this.handleScroll(e));
        
        // Click events
        this.container.addEventListener('click', (e) => {
            const itemElement = e.target.closest('.animated-item');
            if (itemElement) {
                const index = parseInt(itemElement.dataset.index);
                this.selectItem(index);
            }
        });
        
        // Hover events
        this.container.addEventListener('mouseover', (e) => {
            const itemElement = e.target.closest('.animated-item');
            if (itemElement) {
                const index = parseInt(itemElement.dataset.index);
                this.selectedIndex = index;
                this.updateSelection();
            }
        });
        
        // Keyboard navigation
        if (this.options.enableArrowNavigation) {
            document.addEventListener('keydown', (e) => this.handleKeyDown(e));
        }
        
        // Animation on scroll into view
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                }
            });
        }, { threshold: 0.5 });
        
        this.container.querySelectorAll('.animated-item').forEach(item => {
            observer.observe(item);
        });
    }
    
    handleScroll(e) {
        if (!this.options.showGradients) return;
        
        const { scrollTop, scrollHeight, clientHeight } = e.target;
        this.topGradientOpacity = Math.min(scrollTop / 50, 1);
        const bottomDistance = scrollHeight - (scrollTop + clientHeight);
        this.bottomGradientOpacity = scrollHeight <= clientHeight ? 0 : Math.min(bottomDistance / 50, 1);
        
        if (this.topGradient) this.topGradient.style.opacity = this.topGradientOpacity;
        if (this.bottomGradient) this.bottomGradient.style.opacity = this.bottomGradientOpacity;
    }
    
    handleKeyDown(e) {
        const isInContainer = this.container.contains(document.activeElement) || 
                             this.container === document.activeElement ||
                             document.activeElement === document.body;
        
        if (!isInContainer) return;
        
        if (e.key === 'ArrowDown' || (e.key === 'Tab' && !e.shiftKey)) {
            e.preventDefault();
            this.keyboardNav = true;
            this.selectedIndex = Math.min(this.selectedIndex + 1, this.options.items.length - 1);
            this.updateSelection();
            this.scrollToSelected();
        } else if (e.key === 'ArrowUp' || (e.key === 'Tab' && e.shiftKey)) {
            e.preventDefault();
            this.keyboardNav = true;
            this.selectedIndex = Math.max(this.selectedIndex - 1, 0);
            this.updateSelection();
            this.scrollToSelected();
        } else if (e.key === 'Enter') {
            if (this.selectedIndex >= 0 && this.selectedIndex < this.options.items.length) {
                e.preventDefault();
                this.selectItem(this.selectedIndex);
            }
        }
    }
    
    selectItem(index) {
        this.selectedIndex = index;
        this.updateSelection();
        
        if (this.options.onItemSelect) {
            this.options.onItemSelect(this.options.items[index], index);
        }
    }
    
    updateSelection() {
        this.container.querySelectorAll('.item').forEach((item, index) => {
            item.classList.toggle('selected', index === this.selectedIndex);
        });
    }
    
    scrollToSelected() {
        if (!this.keyboardNav || this.selectedIndex < 0) return;
        
        const selectedItem = this.container.querySelector(`[data-index="${this.selectedIndex}"]`);
        if (!selectedItem) return;
        
        const container = this.listElement;
        const extraMargin = 50;
        const containerScrollTop = container.scrollTop;
        const containerHeight = container.clientHeight;
        const itemTop = selectedItem.offsetTop;
        const itemBottom = itemTop + selectedItem.offsetHeight;
        
        if (itemTop < containerScrollTop + extraMargin) {
            container.scrollTo({ 
                top: itemTop - extraMargin, 
                behavior: 'smooth' 
            });
        } else if (itemBottom > containerScrollTop + containerHeight - extraMargin) {
            container.scrollTo({ 
                top: itemBottom - containerHeight + extraMargin, 
                behavior: 'smooth' 
            });
        }
        
        this.keyboardNav = false;
    }
    
    updateItems(newItems) {
        this.options.items = newItems;
        this.selectedIndex = -1;
        this.createStructure();
        this.bindEvents();
    }
    
    destroy() {
        document.removeEventListener('keydown', this.handleKeyDown);
    }
}

// Export for global use
window.AnimatedList = AnimatedList;