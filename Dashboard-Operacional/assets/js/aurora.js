class Aurora {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            colorStops: options.colorStops || ["#3A29FF", "#FF94B4", "#FF3232"],
            blend: options.blend || 0.5,
            amplitude: options.amplitude || 1.0,
            speed: options.speed || 0.5,
            ...options
        };
        
        this.canvas = null;
        this.gl = null;
        this.program = null;
        this.startTime = Date.now();
        this.animationId = null;
        
        this.init();
    }
    
    init() {
        this.createCanvas();
        this.initWebGL();
        this.createShaderProgram();
        this.setupGeometry();
        this.resize();
        this.animate();
        
        window.addEventListener('resize', () => this.resize());
    }
    
    createCanvas() {
        this.canvas = document.createElement('canvas');
        this.canvas.style.position = 'fixed';
        this.canvas.style.top = '0';
        this.canvas.style.left = '0';
        this.canvas.style.width = '100%';
        this.canvas.style.height = '100%';
        this.canvas.style.zIndex = '-1';
        this.canvas.style.pointerEvents = 'none';
        this.container.appendChild(this.canvas);
    }
    
    initWebGL() {
        this.gl = this.canvas.getContext('webgl2') || this.canvas.getContext('webgl');
        if (!this.gl) {
            console.error('WebGL não suportado');
            return;
        }
        
        this.gl.clearColor(0, 0, 0, 0);
        this.gl.enable(this.gl.BLEND);
        this.gl.blendFunc(this.gl.ONE, this.gl.ONE_MINUS_SRC_ALPHA);
    }
    
    createShader(type, source) {
        const shader = this.gl.createShader(type);
        this.gl.shaderSource(shader, source);
        this.gl.compileShader(shader);
        
        if (!this.gl.getShaderParameter(shader, this.gl.COMPILE_STATUS)) {
            console.error('Erro ao compilar shader:', this.gl.getShaderInfoLog(shader));
            this.gl.deleteShader(shader);
            return null;
        }
        
        return shader;
    }
    
    createShaderProgram() {
        const vertexShaderSource = `
            attribute vec2 a_position;
            void main() {
                gl_Position = vec4(a_position, 0.0, 1.0);
            }
        `;
        
        const fragmentShaderSource = `
            precision highp float;
            
            uniform float u_time;
            uniform float u_amplitude;
            uniform vec3 u_colorStops[3];
            uniform vec2 u_resolution;
            uniform float u_blend;
            
            vec3 permute(vec3 x) {
                return mod(((x * 34.0) + 1.0) * x, 289.0);
            }
            
            float snoise(vec2 v) {
                const vec4 C = vec4(0.211324865405187, 0.366025403784439, -0.577350269189626, 0.024390243902439);
                vec2 i = floor(v + dot(v, C.yy));
                vec2 x0 = v - i + dot(i, C.xx);
                vec2 i1 = (x0.x > x0.y) ? vec2(1.0, 0.0) : vec2(0.0, 1.0);
                vec4 x12 = x0.xyxy + C.xxzz;
                x12.xy -= i1;
                i = mod(i, 289.0);
                
                vec3 p = permute(permute(i.y + vec3(0.0, i1.y, 1.0)) + i.x + vec3(0.0, i1.x, 1.0));
                
                vec3 m = max(0.5 - vec3(dot(x0, x0), dot(x12.xy, x12.xy), dot(x12.zw, x12.zw)), 0.0);
                m = m * m;
                m = m * m;
                
                vec3 x = 2.0 * fract(p * C.www) - 1.0;
                vec3 h = abs(x) - 0.5;
                vec3 ox = floor(x + 0.5);
                vec3 a0 = x - ox;
                m *= 1.79284291400159 - 0.85373472095314 * (a0*a0 + h*h);
                
                vec3 g;
                g.x = a0.x * x0.x + h.x * x0.y;
                g.yz = a0.yz * x12.xz + h.yz * x12.yw;
                return 130.0 * dot(m, g);
            }
            
            vec3 getColorFromGradient(float factor) {
                if (factor <= 0.5) {
                    return mix(u_colorStops[0], u_colorStops[1], factor * 2.0);
                } else {
                    return mix(u_colorStops[1], u_colorStops[2], (factor - 0.5) * 2.0);
                }
            }
            
            void main() {
                vec2 uv = gl_FragCoord.xy / u_resolution;
                
                vec3 rampColor = getColorFromGradient(uv.x);
                
                float height = snoise(vec2(uv.x * 2.0 + u_time * 0.1, u_time * 0.25)) * 0.5 * u_amplitude;
                height = exp(height);
                height = (uv.y * 2.0 - height + 0.2);
                float intensity = 0.6 * height;
                
                float midPoint = 0.20;
                float auroraAlpha = smoothstep(midPoint - u_blend * 0.5, midPoint + u_blend * 0.5, intensity);
                
                vec3 auroraColor = intensity * rampColor;
                
                gl_FragColor = vec4(auroraColor * auroraAlpha, auroraAlpha * 0.7);
            }
        `;
        
        const vertexShader = this.createShader(this.gl.VERTEX_SHADER, vertexShaderSource);
        const fragmentShader = this.createShader(this.gl.FRAGMENT_SHADER, fragmentShaderSource);
        
        this.program = this.gl.createProgram();
        this.gl.attachShader(this.program, vertexShader);
        this.gl.attachShader(this.program, fragmentShader);
        this.gl.linkProgram(this.program);
        
        if (!this.gl.getProgramParameter(this.program, this.gl.LINK_STATUS)) {
            console.error('Erro ao linkar programa:', this.gl.getProgramInfoLog(this.program));
            return;
        }
        
        this.gl.useProgram(this.program);
        
        // Get uniform locations
        this.uniforms = {
            time: this.gl.getUniformLocation(this.program, 'u_time'),
            amplitude: this.gl.getUniformLocation(this.program, 'u_amplitude'),
            colorStops: this.gl.getUniformLocation(this.program, 'u_colorStops'),
            resolution: this.gl.getUniformLocation(this.program, 'u_resolution'),
            blend: this.gl.getUniformLocation(this.program, 'u_blend')
        };
    }
    
    setupGeometry() {
        const vertices = new Float32Array([
            -1, -1,
             1, -1,
            -1,  1,
            -1,  1,
             1, -1,
             1,  1
        ]);
        
        const buffer = this.gl.createBuffer();
        this.gl.bindBuffer(this.gl.ARRAY_BUFFER, buffer);
        this.gl.bufferData(this.gl.ARRAY_BUFFER, vertices, this.gl.STATIC_DRAW);
        
        const positionLocation = this.gl.getAttribLocation(this.program, 'a_position');
        this.gl.enableVertexAttribArray(positionLocation);
        this.gl.vertexAttribPointer(positionLocation, 2, this.gl.FLOAT, false, 0, 0);
    }
    
    hexToRgb(hex) {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? [
            parseInt(result[1], 16) / 255,
            parseInt(result[2], 16) / 255,
            parseInt(result[3], 16) / 255
        ] : [1, 1, 1];
    }
    
    resize() {
        const rect = this.container.getBoundingClientRect();
        this.canvas.width = window.innerWidth;
        this.canvas.height = window.innerHeight;
        this.gl.viewport(0, 0, this.canvas.width, this.canvas.height);
        
        if (this.uniforms && this.uniforms.resolution) {
            this.gl.uniform2f(this.uniforms.resolution, this.canvas.width, this.canvas.height);
        }
    }
    
    animate() {
        const currentTime = (Date.now() - this.startTime) * 0.001;
        
        this.gl.clear(this.gl.COLOR_BUFFER_BIT);
        
        // Update uniforms
        this.gl.uniform1f(this.uniforms.time, currentTime * this.options.speed);
        this.gl.uniform1f(this.uniforms.amplitude, this.options.amplitude);
        this.gl.uniform1f(this.uniforms.blend, this.options.blend);
        
        // Convert hex colors to RGB
        const colors = this.options.colorStops.map(hex => this.hexToRgb(hex));
        this.gl.uniform3fv(this.uniforms.colorStops, colors.flat());
        
        // Draw
        this.gl.drawArrays(this.gl.TRIANGLES, 0, 6);
        
        this.animationId = requestAnimationFrame(() => this.animate());
    }
    
    destroy() {
        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
        }
        if (this.canvas && this.canvas.parentNode) {
            this.canvas.parentNode.removeChild(this.canvas);
        }
        if (this.gl) {
            const ext = this.gl.getExtension('WEBGL_lose_context');
            if (ext) ext.loseContext();
        }
    }
    
    updateOptions(newOptions) {
        this.options = { ...this.options, ...newOptions };
    }
}

// Export for global use
window.Aurora = Aurora;