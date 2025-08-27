// Utility functions
function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

function lerp(p1, p2, t) {
    return p1 + (p2 - p1) * t;
}

function autoBind(instance) {
    const proto = Object.getPrototypeOf(instance);
    Object.getOwnPropertyNames(proto).forEach((key) => {
        if (key !== "constructor" && typeof instance[key] === "function") {
            instance[key] = instance[key].bind(instance);
        }
    });
}

// Simple 3D Math utilities for vanilla JS
class Vector3 {
    constructor(x = 0, y = 0, z = 0) {
        this.x = x;
        this.y = y;
        this.z = z;
    }
    
    set(x, y, z) {
        this.x = x;
        this.y = y;
        this.z = z;
        return this;
    }
}

class Matrix4 {
    constructor() {
        this.elements = [
            1, 0, 0, 0,
            0, 1, 0, 0,
            0, 0, 1, 0,
            0, 0, 0, 1
        ];
    }
    
    perspective(fov, aspect, near, far) {
        const f = Math.tan(Math.PI * 0.5 - 0.5 * fov);
        const rangeInv = 1.0 / (near - far);
        
        this.elements = [
            f / aspect, 0, 0, 0,
            0, f, 0, 0,
            0, 0, (near + far) * rangeInv, -1,
            0, 0, near * far * rangeInv * 2, 0
        ];
        return this;
    }
}

// WebGL Renderer
class SimpleRenderer {
    constructor(canvas) {
        this.canvas = canvas;
        this.gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
        
        if (!this.gl) {
            throw new Error('WebGL not supported');
        }
        
        this.gl.enable(this.gl.DEPTH_TEST);
        this.gl.enable(this.gl.BLEND);
        this.gl.blendFunc(this.gl.SRC_ALPHA, this.gl.ONE_MINUS_SRC_ALPHA);
        
        this.programs = new Map();
        this.textures = new Map();
    }
    
    setSize(width, height) {
        this.canvas.width = width;
        this.canvas.height = height;
        this.gl.viewport(0, 0, width, height);
    }
    
    createShaderProgram(vertexSource, fragmentSource) {
        const vertexShader = this.createShader(this.gl.VERTEX_SHADER, vertexSource);
        const fragmentShader = this.createShader(this.gl.FRAGMENT_SHADER, fragmentSource);
        
        const program = this.gl.createProgram();
        this.gl.attachShader(program, vertexShader);
        this.gl.attachShader(program, fragmentShader);
        this.gl.linkProgram(program);
        
        if (!this.gl.getProgramParameter(program, this.gl.LINK_STATUS)) {
            console.error('Program linking error:', this.gl.getProgramInfoLog(program));
            return null;
        }
        
        return program;
    }
    
    createShader(type, source) {
        const shader = this.gl.createShader(type);
        this.gl.shaderSource(shader, source);
        this.gl.compileShader(shader);
        
        if (!this.gl.getShaderParameter(shader, this.gl.COMPILE_STATUS)) {
            console.error('Shader compilation error:', this.gl.getShaderInfoLog(shader));
            return null;
        }
        
        return shader;
    }
    
    createTexture(image) {
        const texture = this.gl.createTexture();
        this.gl.bindTexture(this.gl.TEXTURE_2D, texture);
        
        this.gl.texImage2D(this.gl.TEXTURE_2D, 0, this.gl.RGBA, this.gl.RGBA, this.gl.UNSIGNED_BYTE, image);
        this.gl.generateMipmaps(this.gl.TEXTURE_2D);
        
        this.gl.texParameteri(this.gl.TEXTURE_2D, this.gl.TEXTURE_WRAP_S, this.gl.CLAMP_TO_EDGE);
        this.gl.texParameteri(this.gl.TEXTURE_2D, this.gl.TEXTURE_WRAP_T, this.gl.CLAMP_TO_EDGE);
        this.gl.texParameteri(this.gl.TEXTURE_2D, this.gl.TEXTURE_MIN_FILTER, this.gl.LINEAR_MIPMAP_LINEAR);
        this.gl.texParameteri(this.gl.TEXTURE_2D, this.gl.TEXTURE_MAG_FILTER, this.gl.LINEAR);
        
        return texture;
    }
    
    clear() {
        this.gl.clearColor(0, 0, 0, 0);
        this.gl.clear(this.gl.COLOR_BUFFER_BIT | this.gl.DEPTH_BUFFER_BIT);
    }
}

// Media item class
class Media {
    constructor(options) {
        this.image = options.image;
        this.text = options.text;
        this.index = options.index;
        this.length = options.length;
        this.renderer = options.renderer;
        this.bend = options.bend || 1;
        this.textColor = options.textColor || '#ffffff';
        this.borderRadius = options.borderRadius || 0;
        
        this.position = new Vector3();
        this.rotation = new Vector3();
        this.scale = new Vector3(1, 1, 1);
        
        this.element = null;
        this.imageElement = null;
        this.textElement = null;
        
        this.extra = 0;
        this.width = 0;
        this.widthTotal = 0;
        this.x = 0;
        
        this.createElements();
    }
    
    createElements() {
        // Create main container
        this.element = document.createElement('div');
        this.element.className = 'gallery-item';
        this.element.style.cssText = `
            position: absolute;
            width: 300px;
            height: 200px;
            transform-style: preserve-3d;
            cursor: pointer;
            transition: all 0.3s ease;
        `;
        
        // Create image element
        this.imageElement = document.createElement('div');
        this.imageElement.className = 'gallery-image';
        this.imageElement.style.cssText = `
            width: 100%;
            height: 80%;
            background-image: url(${this.image});
            background-size: cover;
            background-position: center;
            border-radius: ${this.borderRadius * 100}px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        `;
        
        // Create text element
        this.textElement = document.createElement('div');
        this.textElement.className = 'gallery-text';
        this.textElement.textContent = this.text;
        this.textElement.style.cssText = `
            position: absolute;
            bottom: -30px;
            left: 50%;
            transform: translateX(-50%);
            color: ${this.textColor};
            font-size: 16px;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
            opacity: 0.9;
        `;
        
        this.element.appendChild(this.imageElement);
        this.element.appendChild(this.textElement);
        
        // Add hover effects
        this.element.addEventListener('mouseenter', () => {
            this.imageElement.style.transform = 'scale(1.05) translateZ(20px)';
            this.textElement.style.opacity = '1';
        });
        
        this.element.addEventListener('mouseleave', () => {
            this.imageElement.style.transform = 'scale(1) translateZ(0px)';
            this.textElement.style.opacity = '0.9';
        });
    }
    
    update(scroll, direction, viewport) {
        this.position.x = this.x - scroll.current - this.extra;
        
        const x = this.position.x;
        const H = viewport.width / 2;
        
        if (this.bend === 0) {
            this.position.y = 0;
            this.rotation.z = 0;
        } else {
            const B_abs = Math.abs(this.bend);
            const R = (H * H + B_abs * B_abs) / (2 * B_abs);
            const effectiveX = Math.min(Math.abs(x), H);
            
            const arc = R - Math.sqrt(R * R - effectiveX * effectiveX);
            if (this.bend > 0) {
                this.position.y = -arc;
                this.rotation.z = -Math.sign(x) * Math.asin(effectiveX / R);
            } else {
                this.position.y = arc;
                this.rotation.z = Math.sign(x) * Math.asin(effectiveX / R);
            }
        }
        
        // Update element transform
        this.element.style.transform = `
            translate3d(${this.position.x + viewport.width/2}px, ${this.position.y + viewport.height/2}px, 0)
            rotateZ(${this.rotation.z}rad)
            scale(${this.scale.x}, ${this.scale.y})
        `;
        
        // Handle infinite scroll
        const planeOffset = this.width / 2;
        const viewportOffset = viewport.width / 2;
        this.isBefore = this.position.x + planeOffset < -viewportOffset;
        this.isAfter = this.position.x - planeOffset > viewportOffset;
        
        if (direction === "right" && this.isBefore) {
            this.extra -= this.widthTotal;
            this.isBefore = this.isAfter = false;
        }
        if (direction === "left" && this.isAfter) {
            this.extra += this.widthTotal;
            this.isBefore = this.isAfter = false;
        }
    }
    
    onResize(viewport) {
        const scaleValue = Math.min(viewport.width / 1200, viewport.height / 800);
        this.scale.set(scaleValue, scaleValue, 1);
        
        this.width = 300 * scaleValue + 50; // 50px padding
        this.widthTotal = this.width * this.length;
        this.x = this.width * this.index;
    }
}

// Main CircularGallery class
class CircularGallery {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            items: options.items || this.getDefaultItems(),
            bend: options.bend || 3,
            textColor: options.textColor || "#ffffff",
            borderRadius: options.borderRadius || 0.05,
            scrollSpeed: options.scrollSpeed || 2,
            scrollEase: options.scrollEase || 0.05,
            ...options
        };
        
        this.scroll = { 
            ease: this.options.scrollEase, 
            current: 0, 
            target: 0, 
            last: 0 
        };
        
        this.viewport = { width: 0, height: 0 };
        this.medias = [];
        this.isDown = false;
        this.start = 0;
        this.raf = null;
        
        autoBind(this);
        this.onCheckDebounce = debounce(this.onCheck, 200);
        
        this.init();
    }
    
    getDefaultItems() {
        return [
            { image: `https://picsum.photos/seed/1/400/300`, text: "User 1" },
            { image: `https://picsum.photos/seed/2/400/300`, text: "User 2" },
            { image: `https://picsum.photos/seed/3/400/300`, text: "User 3" },
            { image: `https://picsum.photos/seed/4/400/300`, text: "User 4" },
            { image: `https://picsum.photos/seed/5/400/300`, text: "User 5" },
            { image: `https://picsum.photos/seed/6/400/300`, text: "User 6" },
            { image: `https://picsum.photos/seed/7/400/300`, text: "User 7" },
            { image: `https://picsum.photos/seed/8/400/300`, text: "User 8" }
        ];
    }
    
    init() {
        this.createContainer();
        this.createMedias();
        this.onResize();
        this.update();
        this.addEventListeners();
    }
    
    createContainer() {
        this.container.style.cssText = `
            position: relative;
            width: 100%;
            height: 100%;
            overflow: hidden;
            cursor: grab;
            background: transparent;
        `;
        
        this.container.setAttribute('data-cursor', 'grab');
    }
    
    createMedias() {
        const galleryItems = this.options.items.concat(this.options.items); // Duplicate for infinite scroll
        
        this.medias = galleryItems.map((data, index) => {
            const media = new Media({
                image: data.image,
                text: data.text,
                index,
                length: galleryItems.length,
                bend: this.options.bend,
                textColor: this.options.textColor,
                borderRadius: this.options.borderRadius
            });
            
            this.container.appendChild(media.element);
            return media;
        });
    }
    
    onTouchDown(e) {
        this.isDown = true;
        this.container.style.cursor = 'grabbing';
        this.container.setAttribute('data-cursor', 'grabbing');
        
        this.scroll.position = this.scroll.current;
        this.start = e.touches ? e.touches[0].clientX : e.clientX;
    }
    
    onTouchMove(e) {
        if (!this.isDown) return;
        
        const x = e.touches ? e.touches[0].clientX : e.clientX;
        const distance = (this.start - x) * (this.options.scrollSpeed * 0.05);
        this.scroll.target = this.scroll.position + distance;
    }
    
    onTouchUp() {
        this.isDown = false;
        this.container.style.cursor = 'grab';
        this.container.setAttribute('data-cursor', 'grab');
        this.onCheck();
    }
    
    onWheel(e) {
        const delta = e.deltaY || e.wheelDelta || e.detail;
        this.scroll.target += (delta > 0 ? this.options.scrollSpeed : -this.options.scrollSpeed) * 0.3;
        this.onCheckDebounce();
    }
    
    onCheck() {
        if (!this.medias || !this.medias[0]) return;
        
        const width = this.medias[0].width;
        const itemIndex = Math.round(Math.abs(this.scroll.target) / width);
        const item = width * itemIndex;
        this.scroll.target = this.scroll.target < 0 ? -item : item;
    }
    
    onResize() {
        this.viewport = {
            width: this.container.clientWidth,
            height: this.container.clientHeight
        };
        
        if (this.medias) {
            this.medias.forEach(media => media.onResize(this.viewport));
        }
    }
    
    update() {
        this.scroll.current = lerp(this.scroll.current, this.scroll.target, this.scroll.ease);
        const direction = this.scroll.current > this.scroll.last ? "right" : "left";
        
        if (this.medias) {
            this.medias.forEach(media => media.update(this.scroll, direction, this.viewport));
        }
        
        this.scroll.last = this.scroll.current;
        this.raf = requestAnimationFrame(this.update);
    }
    
    addEventListeners() {
        // Resize
        window.addEventListener('resize', this.onResize);
        
        // Mouse/Touch events
        this.container.addEventListener('mousedown', this.onTouchDown);
        this.container.addEventListener('mousemove', this.onTouchMove);
        this.container.addEventListener('mouseup', this.onTouchUp);
        this.container.addEventListener('mouseleave', this.onTouchUp);
        
        this.container.addEventListener('touchstart', this.onTouchDown);
        this.container.addEventListener('touchmove', this.onTouchMove);
        this.container.addEventListener('touchend', this.onTouchUp);
        
        // Wheel
        this.container.addEventListener('wheel', this.onWheel);
        
        // Prevent context menu
        this.container.addEventListener('contextmenu', e => e.preventDefault());
    }
    
    destroy() {
        if (this.raf) {
            cancelAnimationFrame(this.raf);
        }
        
        window.removeEventListener('resize', this.onResize);
        this.container.removeEventListener('mousedown', this.onTouchDown);
        this.container.removeEventListener('mousemove', this.onTouchMove);
        this.container.removeEventListener('mouseup', this.onTouchUp);
        this.container.removeEventListener('mouseleave', this.onTouchUp);
        this.container.removeEventListener('touchstart', this.onTouchDown);
        this.container.removeEventListener('touchmove', this.onTouchMove);
        this.container.removeEventListener('touchend', this.onTouchUp);
        this.container.removeEventListener('wheel', this.onWheel);
        
        this.container.innerHTML = '';
    }
}

// Export for global use
window.CircularGallery = CircularGallery;