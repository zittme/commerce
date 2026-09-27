(function (global) {
	'use strict';

	var EC_M = [
		null,
		[10, 1, 16, 0, 0], [16, 1, 28, 0, 0], [26, 1, 44, 0, 0], [18, 2, 32, 0, 0], [24, 2, 43, 0, 0],
		[16, 4, 27, 0, 0], [18, 4, 31, 0, 0], [22, 2, 38, 2, 39], [22, 3, 36, 2, 37], [26, 4, 43, 1, 44],
		[30, 1, 50, 4, 51], [22, 6, 36, 2, 37], [22, 8, 37, 1, 38], [24, 4, 40, 5, 41], [24, 5, 41, 5, 42],
		[28, 7, 45, 3, 46], [28, 10, 46, 1, 47], [26, 9, 43, 4, 44], [26, 3, 44, 11, 45], [26, 3, 41, 13, 42]
	];

	var ALIGN = [
		null, [], [6, 18], [6, 22], [6, 26], [6, 30], [6, 34], [6, 22, 38], [6, 24, 42], [6, 26, 46], [6, 28, 50],
		[6, 30, 54], [6, 32, 58], [6, 34, 62], [6, 26, 46, 66], [6, 26, 48, 70], [6, 26, 50, 74], [6, 30, 54, 78],
		[6, 30, 56, 82], [6, 30, 58, 86], [6, 34, 62, 90]
	];

	function utf8(text) {
		var out = [];
		var s = unescape(encodeURIComponent(String(text)));
		for (var i = 0; i < s.length; i++) out.push(s.charCodeAt(i));
		return out;
	}

	function gfMul(x, y) {
		var z = 0;
		for (var i = 7; i >= 0; i--) {
			z = (z << 1) ^ ((z >>> 7) * 0x11D);
			z ^= ((y >>> i) & 1) * x;
		}
		return z & 0xFF;
	}

	function rsDivisor(degree) {
		var result = [];
		for (var i = 0; i < degree - 1; i++) result.push(0);
		result.push(1);
		var root = 1;
		for (i = 0; i < degree; i++) {
			for (var j = 0; j < result.length; j++) {
				result[j] = gfMul(result[j], root);
				if (j + 1 < result.length) result[j] ^= result[j + 1];
			}
			root = gfMul(root, 0x02);
		}
		return result;
	}

	function rsRemainder(data, divisor) {
		var result = divisor.map(function () { return 0; });
		data.forEach(function (b) {
			var factor = b ^ result.shift();
			result.push(0);
			for (var i = 0; i < divisor.length; i++) result[i] ^= gfMul(divisor[i], factor);
		});
		return result;
	}

	function dataCapacity(ver) {
		var t = EC_M[ver];
		return t[1] * t[2] + t[3] * t[4];
	}

	function encode(text) {
		var bytes = utf8(text);
		var ver = 0;
		for (var v = 1; v < EC_M.length; v++) {
			var countBits = v < 10 ? 8 : 16;
			if (4 + countBits + bytes.length * 8 <= dataCapacity(v) * 8) { ver = v; break; }
		}
		if (!ver) throw new Error('QR data too long');

		var bits = [];
		function put(val, len) { for (var i = len - 1; i >= 0; i--) bits.push((val >>> i) & 1); }
		put(4, 4);
		put(bytes.length, ver < 10 ? 8 : 16);
		bytes.forEach(function (b) { put(b, 8); });
		var capBits = dataCapacity(ver) * 8;
		put(0, Math.min(4, capBits - bits.length));
		put(0, (8 - bits.length % 8) % 8);
		for (var pad = 0xEC; bits.length < capBits; pad ^= 0xEC ^ 0x11) put(pad, 8);
		var data = [];
		for (var i = 0; i < bits.length; i += 8) {
			var byte = 0;
			for (var k = 0; k < 8; k++) byte = (byte << 1) | bits[i + k];
			data.push(byte);
		}

		var t = EC_M[ver];
		var ecLen = t[0];
		var divisor = rsDivisor(ecLen);
		var blocks = [];
		var pos = 0;
		function addBlocks(count, len) {
			for (var b = 0; b < count; b++) {
				var d = data.slice(pos, pos + len);
				pos += len;
				blocks.push({ data: d, ecc: rsRemainder(d, divisor) });
			}
		}
		addBlocks(t[1], t[2]);
		addBlocks(t[3], t[4]);
		var maxLen = Math.max(t[2], t[4]);
		var final = [];
		for (i = 0; i < maxLen; i++) blocks.forEach(function (blk) { if (i < blk.data.length) final.push(blk.data[i]); });
		for (i = 0; i < ecLen; i++) blocks.forEach(function (blk) { final.push(blk.ecc[i]); });
		return { version: ver, codewords: final };
	}

	function build(text) {
		var enc = encode(text);
		var ver = enc.version;
		var size = ver * 4 + 17;
		var mod = [], fn = [];
		for (var y = 0; y < size; y++) { mod.push(new Array(size).fill(false)); fn.push(new Array(size).fill(false)); }
		function setF(x, y, dark) { mod[y][x] = dark; fn[y][x] = true; }

		for (var i = 0; i < size; i++) { setF(6, i, i % 2 === 0); setF(i, 6, i % 2 === 0); }
		function finder(cx, cy) {
			for (var dy = -4; dy <= 4; dy++) {
				for (var dx = -4; dx <= 4; dx++) {
					var d = Math.max(Math.abs(dx), Math.abs(dy));
					var xx = cx + dx, yy = cy + dy;
					if (xx >= 0 && xx < size && yy >= 0 && yy < size) setF(xx, yy, d !== 2 && d !== 4);
				}
			}
		}
		finder(3, 3); finder(size - 4, 3); finder(3, size - 4);
		var al = ALIGN[ver];
		for (i = 0; i < al.length; i++) {
			for (var j = 0; j < al.length; j++) {
				if ((i === 0 && j === 0) || (i === 0 && j === al.length - 1) || (i === al.length - 1 && j === 0)) continue;
				for (var ady = -2; ady <= 2; ady++) {
					for (var adx = -2; adx <= 2; adx++) setF(al[i] + adx, al[j] + ady, Math.max(Math.abs(adx), Math.abs(ady)) !== 1);
				}
			}
		}
		function formatBits(mask) {
			var d = (0 << 3) | mask;
			var rem = d;
			for (var n = 0; n < 10; n++) rem = (rem << 1) ^ ((rem >>> 9) * 0x537);
			var b = ((d << 10) | rem) ^ 0x5412;
			function bit(k) { return ((b >>> k) & 1) !== 0; }
			for (n = 0; n <= 5; n++) setF(8, n, bit(n));
			setF(8, 7, bit(6)); setF(8, 8, bit(7)); setF(7, 8, bit(8));
			for (n = 9; n < 15; n++) setF(14 - n, 8, bit(n));
			for (n = 0; n < 8; n++) setF(size - 1 - n, 8, bit(n));
			for (n = 8; n < 15; n++) setF(8, size - 15 + n, bit(n));
			setF(8, size - 8, true);
		}
		formatBits(0);
		if (ver >= 7) {
			var rem = ver;
			for (i = 0; i < 12; i++) rem = (rem << 1) ^ ((rem >>> 11) * 0x1F25);
			var vb = (ver << 12) | rem;
			for (i = 0; i < 18; i++) {
				var bt = ((vb >>> i) & 1) !== 0;
				var a = size - 11 + (i % 3), b2 = Math.floor(i / 3);
				setF(a, b2, bt); setF(b2, a, bt);
			}
		}

		var cw = enc.codewords;
		var bi = 0;
		for (var right = size - 1; right >= 1; right -= 2) {
			if (right === 6) right = 5;
			for (var vert = 0; vert < size; vert++) {
				for (j = 0; j < 2; j++) {
					var x = right - j;
					var upward = ((right + 1) & 2) === 0;
					var yy = upward ? size - 1 - vert : vert;
					if (!fn[yy][x] && bi < cw.length * 8) {
						mod[yy][x] = ((cw[bi >>> 3] >>> (7 - (bi & 7))) & 1) !== 0;
						bi++;
					}
				}
			}
		}

		function maskOn(m, x, y) {
			switch (m) {
				case 0: return (x + y) % 2 === 0;
				case 1: return y % 2 === 0;
				case 2: return x % 3 === 0;
				case 3: return (x + y) % 3 === 0;
				case 4: return (Math.floor(x / 3) + Math.floor(y / 2)) % 2 === 0;
				case 5: return (x * y) % 2 + (x * y) % 3 === 0;
				case 6: return ((x * y) % 2 + (x * y) % 3) % 2 === 0;
				default: return ((x + y) % 2 + (x * y) % 3) % 2 === 0;
			}
		}
		function applyMask(m) {
			for (var yy2 = 0; yy2 < size; yy2++) for (var xx2 = 0; xx2 < size; xx2++) if (!fn[yy2][xx2] && maskOn(m, xx2, yy2)) mod[yy2][xx2] = !mod[yy2][xx2];
		}
		function penalty() {
			var score = 0, dark = 0;
			function runs(get) {
				for (var a = 0; a < size; a++) {
					var run = 1;
					for (var b = 1; b <= size; b++) {
						if (b < size && get(a, b) === get(a, b - 1)) { run++; continue; }
						if (run >= 5) score += 3 + run - 5;
						run = 1;
					}
				}
			}
			runs(function (a, b) { return mod[a][b]; });
			runs(function (a, b) { return mod[b][a]; });
			for (var r = 0; r < size - 1; r++) {
				for (var c = 0; c < size - 1; c++) {
					var v = mod[r][c];
					if (v === mod[r][c + 1] && v === mod[r + 1][c] && v === mod[r + 1][c + 1]) score += 3;
				}
			}
			var p1 = [true, false, true, true, true, false, true, false, false, false, false];
			var p2 = [false, false, false, false, true, false, true, true, true, false, true];
			function match(get, a, b, p) { for (var k = 0; k < 11; k++) if (get(a, b + k) !== p[k]) return false; return true; }
			var rowGet = function (a, b) { return mod[a][b]; };
			var colGet = function (a, b) { return mod[b][a]; };
			for (r = 0; r < size; r++) {
				for (c = 0; c + 11 <= size; c++) {
					if (match(rowGet, r, c, p1) || match(rowGet, r, c, p2)) score += 40;
					if (match(colGet, r, c, p1) || match(colGet, r, c, p2)) score += 40;
				}
			}
			for (r = 0; r < size; r++) for (c = 0; c < size; c++) if (mod[r][c]) dark++;
			var total = size * size;
			score += Math.floor(Math.abs(dark * 20 - total * 10) / total) * 10;
			return score;
		}

		var best = 0, bestScore = Infinity;
		for (var m = 0; m < 8; m++) {
			applyMask(m);
			formatBits(m);
			var s = penalty();
			if (s < bestScore) { best = m; bestScore = s; }
			applyMask(m);
		}
		applyMask(best);
		formatBits(best);
		return mod;
	}

	function svg(text, opts) {
		opts = opts || {};
		var m = build(text);
		var n = m.length, q = 4, total = n + q * 2;
		var path = '';
		for (var y = 0; y < n; y++) for (var x = 0; x < n; x++) if (m[y][x]) path += 'M' + (x + q) + ' ' + (y + q) + 'h1v1h-1z';
		var size = opts.size ? ' width="' + opts.size + '" height="' + opts.size + '"' : '';
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + total + ' ' + total + '"' + size + ' shape-rendering="crispEdges">'
			+ '<rect width="' + total + '" height="' + total + '" fill="#ffffff"/><path d="' + path + '" fill="#000000"/></svg>';
	}

	function png(text, scale) {
		var m = build(text);
		var n = m.length, q = 4, s = scale || 12;
		var canvas = document.createElement('canvas');
		canvas.width = canvas.height = (n + q * 2) * s;
		var ctx = canvas.getContext('2d');
		ctx.fillStyle = '#ffffff';
		ctx.fillRect(0, 0, canvas.width, canvas.height);
		ctx.fillStyle = '#000000';
		for (var y = 0; y < n; y++) for (var x = 0; x < n; x++) if (m[y][x]) ctx.fillRect((x + q) * s, (y + q) * s, s, s);
		return canvas.toDataURL('image/png');
	}

	global.ZmcQr = { matrix: build, svg: svg, png: png };
})(window);
