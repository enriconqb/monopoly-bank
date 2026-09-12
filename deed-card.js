/* global THREE, gsap, GROUP_META, getProp, formatMoney, reduceMotion, textOnColor, propCode */

(function () {
    const W = 768;
    const H = 1152;
    const CARD_W = 2.35;
    const CARD_H = 3.52;
    const GOLD = '#c9a227';
    const CREAM = '#f6f0e4';
    const INK = '#1c1408';
    const MUTED = '#6b5a3e';

    let viewer = null;

    function hexToRgb(hex) {
        const h = String(hex || '#1a237e').replace('#', '');
        return {
            r: parseInt(h.slice(0, 2), 16) || 26,
            g: parseInt(h.slice(2, 4), 16) || 35,
            b: parseInt(h.slice(4, 6), 16) || 126
        };
    }

    function roundRect(ctx, x, y, w, h, r) {
        const rr = Math.min(r, w / 2, h / 2);
        ctx.beginPath();
        ctx.moveTo(x + rr, y);
        ctx.arcTo(x + w, y, x + w, y + h, rr);
        ctx.arcTo(x + w, y + h, x, y + h, rr);
        ctx.arcTo(x, y + h, x, y, rr);
        ctx.arcTo(x, y, x + w, y, rr);
        ctx.closePath();
    }

    function drawSkyline(ctx, cx, cy, scale, color) {
        ctx.save();
        ctx.fillStyle = color;
        ctx.translate(cx, cy);
        ctx.scale(scale, scale);
        const blocks = [
            [-90, -28, 18, 28], [-68, -48, 22, 48], [-44, -36, 16, 36],
            [-24, -70, 20, 70], [-2, -42, 18, 42], [18, -86, 24, 86],
            [46, -38, 16, 38], [66, -58, 20, 58], [90, -30, 14, 30]
        ];
        blocks.forEach(function (b) {
            ctx.fillRect(b[0], b[1], b[2], b[3]);
        });
        ctx.beginPath();
        ctx.moveTo(-6, -86);
        ctx.lineTo(6, -86);
        ctx.lineTo(0, -108);
        ctx.closePath();
        ctx.fill();
        ctx.restore();
    }

    function drawLeaderRow(ctx, label, value, x, y, w) {
        ctx.fillStyle = INK;
        ctx.font = '600 28px "Plus Jakarta Sans", system-ui, sans-serif';
        ctx.textAlign = 'left';
        ctx.fillText(label, x, y);
        ctx.textAlign = 'right';
        ctx.font = '800 28px "Plus Jakarta Sans", system-ui, sans-serif';
        ctx.fillText(value, x + w, y);
        const lw = ctx.measureText(label).width;
        const vw = ctx.measureText(value).width;
        ctx.strokeStyle = 'rgba(201,162,39,0.45)';
        ctx.lineWidth = 1.5;
        ctx.setLineDash([3, 6]);
        ctx.beginPath();
        ctx.moveTo(x + lw + 12, y - 8);
        ctx.lineTo(x + w - vw - 12, y - 8);
        ctx.stroke();
        ctx.setLineDash([]);
    }

    function subtitleFor(p) {
        if (p.group === 'railroad') return 'RAILROAD';
        if (p.group === 'utility') return 'UTILITY';
        return 'PRESTIGE PROPERTIES';
    }

    function rentRows(p) {
        const r = p.rentLevels || [];
        if (p.group === 'utility') {
            return [
                ['RENT (1 utility)', '4× dadu'],
                ['Both utilities', '10× dadu']
            ];
        }
        if (p.group === 'railroad') {
            return [
                ['RENT', formatMoney(r[0] || 25)],
                ['With 2 stations', formatMoney(r[1] || 50)],
                ['With 3 stations', formatMoney(r[2] || 100)],
                ['With 4 stations', formatMoney(r[3] || 200)]
            ];
        }
        return [
            ['RENT', formatMoney(r[0] || 0)],
            ['With 1 House', formatMoney(r[1] || 0)],
            ['With 2 Houses', formatMoney(r[2] || 0)],
            ['With 3 Houses', formatMoney(r[3] || 0)],
            ['With 4 Houses', formatMoney(r[4] || 0)],
            ['With Hotel', formatMoney(r[5] || 0)]
        ];
    }

    function wrapTitle(ctx, text, maxW, maxSize, minSize) {
        const raw = String(text || '').toUpperCase().trim();
        const font = function (s) { return '800 ' + s + 'px "Plus Jakarta Sans", system-ui, sans-serif'; };
        function fits(lines, s) {
            ctx.font = font(s);
            return lines.every(function (ln) { return ctx.measureText(ln).width <= maxW; });
        }
        function trySplit(s) {
            const words = raw.split(/\s+/).filter(Boolean);
            if (!words.length) return [''];
            if (fits([raw], s)) return [raw];
            for (let i = 1; i < words.length; i++) {
                const a = words.slice(0, i).join(' ');
                const b = words.slice(i).join(' ');
                if (fits([a, b], s)) return [a, b];
            }
            const mid = Math.ceil(raw.length / 2);
            let cut = mid;
            const sp = raw.lastIndexOf(' ', mid);
            if (sp > 4) cut = sp;
            return [raw.slice(0, cut).trim(), raw.slice(cut).trim()].filter(Boolean);
        }
        for (let s = maxSize; s >= minSize; s--) {
            const lines = trySplit(s);
            if (fits(lines, s)) return { lines: lines, size: s };
        }
        ctx.font = font(minSize);
        return { lines: trySplit(minSize).slice(0, 2), size: minSize };
    }

    function paintFront(p) {
        const c = document.createElement('canvas');
        c.width = W;
        c.height = H;
        const ctx = c.getContext('2d');
        const meta = GROUP_META[p.group] || { color: '#1a237e' };
        const header = meta.color;
        const inkHead = textOnColor(header);
        const code = typeof propCode === 'function' ? propCode(p) : '';

        ctx.fillStyle = CREAM;
        roundRect(ctx, 0, 0, W, H, 36);
        ctx.fill();

        ctx.strokeStyle = GOLD;
        ctx.lineWidth = 10;
        roundRect(ctx, 18, 18, W - 36, H - 36, 28);
        ctx.stroke();
        ctx.lineWidth = 2;
        roundRect(ctx, 32, 32, W - 64, H - 64, 22);
        ctx.stroke();

        const hx = 48, hy = 48, hw = W - 96, hh = 236;
        ctx.fillStyle = header;
        roundRect(ctx, hx, hy, hw, hh, 14);
        ctx.fill();
        ctx.strokeStyle = GOLD;
        ctx.lineWidth = 4;
        roundRect(ctx, hx + 10, hy + 10, hw - 20, hh - 20, 10);
        ctx.stroke();

        const badgeR = 34;
        const bx = hx + hw - 28;
        const by = hy + 36;
        ctx.beginPath();
        ctx.arc(bx, by, badgeR, 0, Math.PI * 2);
        ctx.fillStyle = header;
        ctx.fill();
        ctx.lineWidth = 4;
        ctx.strokeStyle = GOLD;
        ctx.stroke();
        ctx.fillStyle = CREAM;
        ctx.beginPath();
        ctx.arc(bx, by, badgeR - 5, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = header;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.font = '800 22px "Plus Jakarta Sans", system-ui, sans-serif';
        ctx.fillText(code, bx, by + 1);
        ctx.textBaseline = 'alphabetic';

        ctx.fillStyle = inkHead;
        ctx.textAlign = 'center';
        ctx.font = '700 20px "Plus Jakarta Sans", system-ui, sans-serif';
        ctx.fillText('MONOPOLY ENQB', W / 2, hy + 52);
        const titleMax = hw - 100;
        const wrapped = wrapTitle(ctx, p.name, titleMax, 42, 24);
        ctx.font = '800 ' + wrapped.size + 'px "Plus Jakarta Sans", system-ui, sans-serif';
        const lineGap = wrapped.size + 6;
        const startY = wrapped.lines.length === 1 ? hy + 118 : hy + 118 - lineGap / 2;
        wrapped.lines.forEach(function (ln, i) {
            ctx.fillText(ln, W / 2, startY + i * lineGap);
        });
        ctx.font = '600 16px "Plus Jakarta Sans", system-ui, sans-serif';
        ctx.fillText(subtitleFor(p), W / 2, hy + 198);

        const x = 88;
        let y = 352;
        rentRows(p).forEach(function (row) {
            drawLeaderRow(ctx, row[0], row[1], x, y, W - 176);
            y += 52;
        });

        y += 12;
        ctx.strokeStyle = GOLD;
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(x, y);
        ctx.lineTo(W - 88, y);
        ctx.stroke();
        y += 56;
        drawLeaderRow(ctx, 'Mortgage Value', formatMoney(p.mortgage), x, y, W - 176);
        y += 48;
        if (p.houseCost > 0) {
            drawLeaderRow(ctx, 'Houses cost', formatMoney(p.houseCost) + ' each', x, y, W - 176);
            y += 48;
            drawLeaderRow(ctx, 'Hotel cost', formatMoney(p.houseCost) + ' + 4 houses', x, y, W - 176);
        } else {
            drawLeaderRow(ctx, 'Purchase price', formatMoney(p.price), x, y, W - 176);
        }

        drawSkyline(ctx, W / 2, H - 148, 0.55, GOLD);
        ctx.fillStyle = MUTED;
        ctx.textAlign = 'center';
        ctx.font = '700 16px "Plus Jakarta Sans", system-ui, sans-serif';
        ctx.fillText('OWN MORE TOMORROW', W / 2, H - 92);
        ctx.font = '600 14px "Plus Jakarta Sans", system-ui, sans-serif';
        ctx.fillText('© Created by EnricoNQB', W / 2, H - 64);
        return c;
    }

    function paintBack() {
        const c = document.createElement('canvas');
        c.width = W;
        c.height = H;
        const ctx = c.getContext('2d');
        ctx.fillStyle = '#0b0b0d';
        roundRect(ctx, 0, 0, W, H, 36);
        ctx.fill();
        ctx.strokeStyle = GOLD;
        ctx.lineWidth = 8;
        roundRect(ctx, 28, 28, W - 56, H - 56, 26);
        ctx.stroke();
        ctx.lineWidth = 2;
        roundRect(ctx, 48, 48, W - 96, H - 96, 18);
        ctx.stroke();
        roundRect(ctx, 62, 62, W - 124, H - 124, 14);
        ctx.stroke();

        drawSkyline(ctx, W / 2, H / 2 - 80, 1.35, GOLD);

        ctx.strokeStyle = GOLD;
        ctx.lineWidth = 6;
        roundRect(ctx, 140, H / 2 + 40, W - 280, 88, 8);
        ctx.stroke();
        ctx.fillStyle = GOLD;
        ctx.textAlign = 'center';
        ctx.font = '800 48px "Plus Jakarta Sans", system-ui, sans-serif';
        ctx.fillText('MONOPOLY', W / 2, H / 2 + 100);

        ctx.font = '700 18px "Plus Jakarta Sans", system-ui, sans-serif';
        ctx.fillText('PROPERTY BUILDS', W / 2, H / 2 + 180);
        ctx.fillText('A BRIGHTER TOMORROW', W / 2, H / 2 + 208);

        ctx.font = '600 14px "Plus Jakarta Sans", system-ui, sans-serif';
        ctx.fillStyle = 'rgba(201,162,39,0.75)';
        ctx.fillText('© Created by EnricoNQB', W / 2, H - 88);
        return c;
    }

    function holoVertex() {
        return [
            'varying vec2 vUv;',
            'varying vec3 vNormal;',
            'varying vec3 vView;',
            'void main() {',
            '  vUv = uv;',
            '  vec4 wp = modelMatrix * vec4(position, 1.0);',
            '  vNormal = normalize(mat3(modelMatrix) * normal);',
            '  vView = cameraPosition - wp.xyz;',
            '  gl_Position = projectionMatrix * viewMatrix * wp;',
            '}'
        ].join('\n');
    }

    function holoFragment() {
        return [
            'uniform sampler2D map;',
            'uniform float uGloss;',
            'uniform vec2 uPointer;',
            'uniform float uTime;',
            'varying vec2 vUv;',
            'varying vec3 vNormal;',
            'varying vec3 vView;',
            'void main() {',
            '  vec4 base = texture2D(map, vUv);',
            '  vec3 n = normalize(vNormal);',
            '  vec3 v = normalize(vView);',
            '  float fres = pow(1.0 - max(dot(n, v), 0.0), 2.4);',
            '  vec3 lightDir = normalize(vec3(uPointer.x, uPointer.y, 0.85));',
            '  float spec = pow(max(dot(n, normalize(v + lightDir)), 0.0), 28.0);',
            '  vec3 holo = 0.5 + 0.5 * sin(vec3(0.0, 2.094, 4.188) + uTime * 2.4 + fres * 10.0 + uPointer.x * 5.0);',
            '  vec3 gloss = vec3(1.0, 0.93, 0.72) * spec;',
            '  float k = 0.06 + uGloss * 0.55;',
            '  vec3 col = base.rgb + gloss * (0.12 + uGloss * 0.75) + holo * fres * k;',
            '  gl_FragColor = vec4(col, 1.0);',
            '}'
        ].join('\n');
    }

    function makeHoloMat(texture) {
        return new THREE.ShaderMaterial({
            uniforms: {
                map: { value: texture },
                uGloss: { value: 0 },
                uPointer: { value: new THREE.Vector2(0, 0.2) },
                uTime: { value: 0 }
            },
            vertexShader: holoVertex(),
            fragmentShader: holoFragment(),
            side: THREE.FrontSide
        });
    }

    function disposeViewer() {
        if (!viewer) return;
        cancelAnimationFrame(viewer.raf);
        if (viewer.ro) viewer.ro.disconnect();
        window.removeEventListener('resize', viewer.onResize);
        if (viewer.el) {
            viewer.el.removeEventListener('pointerdown', viewer.onDown);
            viewer.el.removeEventListener('pointermove', viewer.onMove);
            viewer.el.removeEventListener('pointerup', viewer.onUp);
            viewer.el.removeEventListener('pointercancel', viewer.onUp);
            viewer.el.removeEventListener('wheel', viewer.onWheel);
        }
        if (viewer.texF) viewer.texF.dispose();
        if (viewer.texB) viewer.texB.dispose();
        if (viewer.frontMat) viewer.frontMat.dispose();
        if (viewer.backMat) viewer.backMat.dispose();
        if (viewer.edgeMat) viewer.edgeMat.dispose();
        if (viewer.geo) viewer.geo.dispose();
        if (viewer.renderer) {
            viewer.renderer.dispose();
            if (viewer.renderer.domElement && viewer.renderer.domElement.parentNode) {
                viewer.renderer.domElement.parentNode.removeChild(viewer.renderer.domElement);
            }
        }
        viewer = null;
    }

    function showStatic(p, host) {
        const c = paintFront(p);
        c.className = 'deed-2d';
        c.style.cssText = '';
        host.appendChild(c);
    }

    window.closeDeedViewer = function () {
        const layer = document.getElementById('deedFxLayer');
        disposeViewer();
        if (layer) {
            layer.classList.remove('open');
            layer.innerHTML = '';
            layer.onclick = null;
        }
    };

    window.openDeedViewer = function (propId) {
        const p = getProp(propId);
        if (!p) return;
        const layer = document.getElementById('deedFxLayer');
        if (!layer) return;
        disposeViewer();
        layer.classList.add('open');
        layer.innerHTML = '<button type="button" class="deed-close" aria-label="Tutup" onclick="closeDeedViewer()"><i class="fa-solid fa-xmark"></i></button>' +
            '<div class="deed-stage" id="deedStage"></div>' +
            '<p class="deed-hint">Tahan lalu geser untuk memutar · lepas untuk kembali</p>';
        const host = document.getElementById('deedStage');
        layer.onclick = function (e) {
            if (e.target === layer) window.closeDeedViewer();
        };

        if (reduceMotion() || typeof THREE === 'undefined') {
            showStatic(p, host);
            return;
        }

        let renderer;
        try {
            renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        } catch (err) {
            showStatic(p, host);
            return;
        }
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        renderer.setClearColor(0x000000, 0);
        renderer.domElement.className = 'deed-3d';
        host.appendChild(renderer.domElement);

        const scene = new THREE.Scene();
        const camera = new THREE.PerspectiveCamera(32, 1, 0.1, 80);

        const texF = new THREE.CanvasTexture(paintFront(p));
        const texB = new THREE.CanvasTexture(paintBack());
        if (THREE.SRGBColorSpace) {
            texF.colorSpace = THREE.SRGBColorSpace;
            texB.colorSpace = THREE.SRGBColorSpace;
        }
        texF.anisotropy = 8;
        texB.anisotropy = 8;
        texB.wrapS = THREE.RepeatWrapping;
        texB.repeat.x = -1;
        texB.offset.x = 1;

        const frontMat = makeHoloMat(texF);
        const backMat = makeHoloMat(texB);
        const edgeMat = new THREE.MeshStandardMaterial({ color: 0xc9a227, metalness: 0.9, roughness: 0.28 });
        const geo = new THREE.BoxGeometry(CARD_W, CARD_H, 0.045);
        const card = new THREE.Mesh(geo, [edgeMat, edgeMat, edgeMat, edgeMat, frontMat, backMat]);
        card.rotation.y = 0.12;
        card.rotation.x = -0.06;
        scene.add(card);
        scene.add(new THREE.AmbientLight(0xfff6e0, 0.85));
        const dir = new THREE.DirectionalLight(0xffffff, 1.15);
        dir.position.set(2.4, 3.2, 4);
        scene.add(dir);

        const idle = { x: -0.06, y: 0.12, z: 0, s: 1 };
        const pose = { x: idle.x, y: idle.y, z: 0, s: 1 };
        card.position.set(0, 0, 0);
        card.scale.set(0.2, 0.2, 0.2);
        if (typeof gsap !== 'undefined') {
            gsap.to(card.scale, { x: 1, y: 1, z: 1, duration: 0.55, ease: 'back.out(1.6)' });
        } else {
            card.scale.set(1, 1, 1);
        }

        const ptr = { down: false, sx: 0, sy: 0, lx: 0, ly: 0 };
        let gloss = 0;
        let vel = 0;

        function fitCamera() {
            const w = host.clientWidth || window.innerWidth;
            const h = host.clientHeight || window.innerHeight;
            renderer.setSize(w, h, false);
            camera.aspect = w / Math.max(h, 1);
            const fov = camera.fov * Math.PI / 180;
            const margin = 1.22;
            const distH = (CARD_H * margin / 2) / Math.tan(fov / 2);
            const distW = (CARD_W * margin / 2) / (Math.tan(fov / 2) * camera.aspect);
            camera.position.z = Math.max(distH, distW, 4);
            camera.updateProjectionMatrix();
        }
        fitCamera();

        function onDown(e) {
            if (e.button != null && e.button !== 0) return;
            e.preventDefault();
            e.stopPropagation();
            host.setPointerCapture(e.pointerId);
            ptr.down = true;
            ptr.sx = ptr.lx = e.clientX;
            ptr.sy = ptr.ly = e.clientY;
        }
        function onMove(e) {
            if (!ptr.down) return;
            e.preventDefault();
            const dx = e.clientX - ptr.lx;
            const dy = e.clientY - ptr.ly;
            ptr.lx = e.clientX;
            ptr.ly = e.clientY;
            vel = Math.min(1, vel + Math.hypot(dx, dy) * 0.02);
            pose.y += dx * 0.012;
            pose.x += dy * 0.01;
            pose.x = Math.max(-0.85, Math.min(0.85, pose.x));
            pose.y = Math.max(-2.8, Math.min(2.8, pose.y));
            const w = host.clientWidth || 1;
            const hh = host.clientHeight || 1;
            frontMat.uniforms.uPointer.value.set((e.clientX / w) * 2 - 1, 1 - (e.clientY / hh) * 2);
            backMat.uniforms.uPointer.value.copy(frontMat.uniforms.uPointer.value);
        }
        function onUp(e) {
            if (e) e.stopPropagation();
            ptr.down = false;
            if (typeof gsap !== 'undefined') {
                gsap.to(pose, { x: idle.x, y: idle.y, z: 0, duration: 0.7, ease: 'power3.out' });
            } else {
                pose.x = idle.x;
                pose.y = idle.y;
            }
        }
        function onWheel(e) {
            e.preventDefault();
            pose.s = Math.max(0.9, Math.min(1.08, pose.s + (e.deltaY > 0 ? -0.04 : 0.04)));
            if (typeof gsap !== 'undefined') gsap.to(card.scale, { x: pose.s, y: pose.s, z: pose.s, duration: 0.2 });
            else card.scale.setScalar(pose.s);
        }

        host.addEventListener('pointerdown', onDown);
        host.addEventListener('pointermove', onMove);
        host.addEventListener('pointerup', onUp);
        host.addEventListener('pointercancel', onUp);
        host.addEventListener('wheel', onWheel, { passive: false });
        const onResize = fitCamera;
        window.addEventListener('resize', onResize);

        const clock = new THREE.Clock();
        function tick() {
            if (!viewer) return;
            viewer.raf = requestAnimationFrame(tick);
            const t = clock.getElapsedTime();
            vel *= 0.92;
            gloss += (vel - gloss) * 0.12;
            card.rotation.x = pose.x;
            card.rotation.y = pose.y;
            card.position.set(0, 0, 0);
            frontMat.uniforms.uTime.value = t;
            backMat.uniforms.uTime.value = t;
            frontMat.uniforms.uGloss.value = gloss;
            backMat.uniforms.uGloss.value = gloss;
            renderer.render(scene, camera);
        }

        viewer = {
            raf: 0, renderer: renderer, geo: geo, frontMat: frontMat, backMat: backMat,
            edgeMat: edgeMat, texF: texF, texB: texB, el: host,
            onDown: onDown, onMove: onMove, onUp: onUp, onWheel: onWheel, onResize: onResize, ro: null
        };
        tick();
    };
})();
