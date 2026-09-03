/**
 * Procedural Open Sea — WebGPURenderer + TSL
 * ------------------------------------------------------------------
 * No framework, no build step, no external assets (no textures, HDRIs,
 * models or video). Everything below is generated analytically on the GPU:
 *
 *   • Ocean surface  : exactly five Gerstner waves, displaced in the vertex
 *                      stage, with the analytic tangent/bitangent pair
 *                      re-evaluated per fragment -> stable swell normals.
 *   • Detail         : animated gradient-noise FBM (ripples + capillary),
 *                      converted to a slope perturbation of the swell normal.
 *   • Shading        : deep-water scattering, Fresnel sky reflection,
 *                      backlit crests (SSS), sun glitter, fold/crest foam,
 *                      exponential horizon haze.
 *   • Sky            : a single analytic radiance function shared by the sky
 *                      dome and the water reflection (sun disk, halo, clouds,
 *                      time-of-day palette) driven by shared uniforms.
 *   • Post           : TSL bloom + ACES filmic tone mapping.
 *
 * Rendering is WebGPU-only on purpose: the WebGL2 fallback of WebGPURenderer
 * is detected and rejected so nothing renders "secretly" through another path.
 */

import {
	ACESFilmicToneMapping,
	BackSide,
	Color,
	MathUtils,
	Mesh,
	MeshBasicNodeMaterial,
	PerspectiveCamera,
	PlaneGeometry,
	RenderPipeline,
	Scene,
	SphereGeometry,
	Vector2,
	Vector3,
	WebGPURenderer
} from 'three/webgpu';

import {
	Fn, float, vec2, vec3, vec4,
	uniform, positionGeometry, positionWorld, cameraPosition,
	abs, clamp, cos, cross, dot, exp, floor, fract, length, max, min, mix,
	normalize, pow, reflect, sin, smoothstep, step, pass
} from 'three/tsl';

import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { bloom } from 'three/addons/tsl/display/BloomNode.js';

// ==================================================================
// 0. DOM plumbing, loading & failure states
// ==================================================================

const dom = {
	canvas: document.getElementById( 'sea' ),
	panel: document.getElementById( 'panel' ),
	panelOpen: document.getElementById( 'panel-open' ),
	panelClose: document.getElementById( 'panel-close' ),
	sea: document.getElementById( 'ctl-sea' ),
	seaOut: document.getElementById( 'out-sea' ),
	time: document.getElementById( 'ctl-time' ),
	timeOut: document.getElementById( 'out-time' ),
	drift: document.getElementById( 'ctl-drift' ),
	driftOut: document.getElementById( 'out-drift' ),
	fps: document.getElementById( 'fps' ),
	fpsLive: document.getElementById( 'fps-live' ),
	quality: document.getElementById( 'quality' ),
	pause: document.getElementById( 'pause' ),
	loading: document.getElementById( 'loading-overlay' ),
	error: document.getElementById( 'error-overlay' ),
	errorTitle: document.getElementById( 'error-title' ),
	errorMessage: document.getElementById( 'error-message' ),
	errorDetail: document.getElementById( 'error-detail' )
};

function fail( title, message, detail = '' ) {

	dom.loading.hidden = true;
	dom.panel.hidden = true;
	dom.panelOpen.hidden = true;
	dom.errorTitle.textContent = title;
	if ( message ) dom.errorMessage.textContent = message;
	dom.errorDetail.textContent = detail ? String( detail ) : '';
	dom.error.hidden = false;

}

const reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );
const isCoarsePointer = window.matchMedia( '(pointer: coarse)' ).matches;

// ==================================================================
// 1. WebGPU detection (explicit — no silent fallback)
// ==================================================================

async function detectWebGPU() {

	if ( typeof navigator === 'undefined' || navigator.gpu === undefined ) {

		return { ok: false, title: 'WebGPU nicht verfügbar', detail: 'navigator.gpu ist nicht definiert.' };

	}

	let adapter = null;

	try {

		adapter = await navigator.gpu.requestAdapter( { powerPreference: 'high-performance' } );

	} catch ( err ) {

		return { ok: false, title: 'WebGPU-Adapter fehlgeschlagen', detail: err.message };

	}

	if ( adapter === null ) {

		return {
			ok: false,
			title: 'Kein WebGPU-Adapter',
			detail: 'requestAdapter() lieferte null — die GPU ist blockiert oder wird nicht unterstützt.'
		};

	}

	return { ok: true, adapter };

}

// ==================================================================
// 2. Shared uniforms (sky dome, water reflection and haze read the same set)
// ==================================================================

const uTime = uniform( 0 );                       // seconds, pausable
const uDrift = uniform( new Vector2( 0, 0 ) );    // slow translation of the whole wave field
const uWindDir = uniform( new Vector2( 1, 0 ) );  // (cos, sin) of the wind bearing

const uAmp = uniform( 1 );        // sea-state amplitude scale
const uChoppy = uniform( 1 );     // Gerstner horizontal steepness scale
const uRipple = uniform( 0.12 );  // mid-scale FBM slope strength
const uCapillary = uniform( 0.05 );
const uFoam = uniform( 0.6 );
const uCrestStart = uniform( 2.0 );
const uCrestEnd = uniform( 4.5 );
const uGlitter = uniform( 1 );
const uDetail = uniform( 1 );

const uSunDir = uniform( new Vector3( 0.3, 0.5, -0.8 ).normalize() );
const uSunColor = uniform( new Color( 1, 0.95, 0.88 ) );
const uSunDisk = uniform( 22 );
const uZenithColor = uniform( new Color( 0.055, 0.16, 0.42 ) );
const uHorizonColor = uniform( new Color( 0.55, 0.70, 0.90 ) );
const uCloudLight = uniform( new Color( 1.0, 0.98, 0.96 ) );
const uCloudDark = uniform( new Color( 0.32, 0.36, 0.44 ) );
const uCloudAmount = uniform( 0.85 );
const uCloudCoverage = uniform( 0.10 );
const uCloudDrift = uniform( new Vector2( 0, 0 ) );
const uDeepColor = uniform( new Color( 0.006, 0.035, 0.055 ) );
const uShallowColor = uniform( new Color( 0.02, 0.13, 0.145 ) );
const uSssColor = uniform( new Color( 0.055, 0.290, 0.240 ) );
const uSss = uniform( 1 );

// ==================================================================
// 3. TSL building blocks — gradient noise & FBM
// ==================================================================

/** 2D hash -> two signed gradients in [-1, 1]. */
const hash22 = /*#__PURE__*/ Fn( ( [ p ] ) => {

	const q = vec2( dot( p, vec2( 127.1, 311.7 ) ), dot( p, vec2( 269.5, 183.3 ) ) );
	return fract( sin( q ).mul( 43758.5453 ) ).mul( 2.0 ).sub( 1.0 );

} );

/** Classic gradient (Perlin-style) noise, roughly [-0.7, 0.7]. */
const gnoise = /*#__PURE__*/ Fn( ( [ p ] ) => {

	const i = floor( p ).toVar();
	const f = fract( p ).toVar();
	const u = f.mul( f ).mul( f.mul( - 2.0 ).add( 3.0 ) ); // smoothstep fade

	const a = dot( hash22( i ), f );
	const b = dot( hash22( i.add( vec2( 1.0, 0.0 ) ) ), f.sub( vec2( 1.0, 0.0 ) ) );
	const c = dot( hash22( i.add( vec2( 0.0, 1.0 ) ) ), f.sub( vec2( 0.0, 1.0 ) ) );
	const d = dot( hash22( i.add( vec2( 1.0, 1.0 ) ) ), f.sub( vec2( 1.0, 1.0 ) ) );

	return mix( mix( a, b, u.x ), mix( c, d, u.x ), u.y );

} );

// Per-octave scroll directions so the octaves never lock into a pattern.
const OCTAVE_FLOW = [
	[ 1.00, 0.17 ], [ - 0.62, 0.78 ], [ 0.34, - 0.94 ], [ - 0.86, - 0.51 ]
];

/**
 * Rotated, animated FBM built by unrolling in JavaScript (graph-time loop).
 * @param {Node<vec2>} p      domain point
 * @param {number} octaves    number of octaves
 * @param {number} speed      scroll speed per octave
 */
function fbm( p, octaves, speed ) {

	let sum = null;
	let amp = 0.5;
	let freq = 1.0;
	let ang = 0.0;

	for ( let i = 0; i < octaves; i ++ ) {

		const c = Math.cos( ang );
		const s = Math.sin( ang );
		const flow = OCTAVE_FLOW[ i % OCTAVE_FLOW.length ];

		const rotated = vec2(
			p.x.mul( c ).sub( p.y.mul( s ) ),
			p.x.mul( s ).add( p.y.mul( c ) )
		).mul( freq ).add( uTime.mul( speed * freq ).mul( vec2( flow[ 0 ], flow[ 1 ] ) ) );

		const octave = gnoise( rotated ).mul( amp );
		sum = sum === null ? octave : sum.add( octave );

		amp *= 0.5;
		freq *= 2.03;
		ang += 0.73;

	}

	return sum;

}

/** Mid-scale ripple height field (world units in, world units out). */
const rippleHeight = /*#__PURE__*/ Fn( ( [ p ] ) => fbm( p.mul( 0.055 ), 3, 0.55 ) );

/** Capillary / glitter height field — one fast octave. */
const capillaryHeight = /*#__PURE__*/ Fn( ( [ p ] ) => fbm( p.mul( 0.42 ), 1, 1.6 ) );

// ==================================================================
// 4. Exactly five Gerstner waves
// ==================================================================

const GRAVITY = 9.81;

// wavelength (m), amplitude (m), steepness, direction offset (rad) vs. wind
const WAVE_TABLE = [
	{ length: 260.0, amplitude: 3.20, steepness: 0.72, angle: 0.00 },
	{ length: 137.0, amplitude: 1.90, steepness: 0.68, angle: 0.38 },
	{ length: 71.0, amplitude: 1.00, steepness: 0.62, angle: - 0.59 },
	{ length: 37.0, amplitude: 0.42, steepness: 0.50, angle: 0.89 },
	{ length: 19.0, amplitude: 0.18, steepness: 0.42, angle: - 1.17 }
].map( ( w ) => {

	const k = ( Math.PI * 2 ) / w.length;      // wave number
	return {
		...w,
		k,
		omega: Math.sqrt( GRAVITY * k ),        // deep-water dispersion
		dx: Math.cos( w.angle ),
		dz: Math.sin( w.angle )
	};

} );

/**
 * Distance LOD for a single wave: a wave whose crests shrink below a few
 * pixels stops contributing — to the geometry *and* to the normal, using the
 * exact same weight, so displacement and shading never disagree. This is what
 * keeps the far field calm instead of shimmering.
 */
function waveLod( wave, viewDistance ) {

	return float( 1.0 ).sub( smoothstep( wave.length * 6.0, wave.length * 22.0, viewDistance ) );

}

/** Wave direction rotated into the current wind frame. */
function waveDirection( wave ) {

	const wc = uWindDir.x;
	const ws = uWindDir.y;
	return vec2(
		wc.mul( wave.dx ).sub( ws.mul( wave.dz ) ),
		ws.mul( wave.dx ).add( wc.mul( wave.dz ) )
	);

}

/**
 * Vertex-stage displacement of the flat grid point `p0` (world XZ).
 * Returns the offset (dx, dy, dz).
 */
const gerstnerDisplacement = /*#__PURE__*/ Fn( ( [ p0, viewDistance ] ) => {

	const displacement = vec3( 0.0 ).toVar();

	for ( const wave of WAVE_TABLE ) {

		const lod = waveLod( wave, viewDistance ).toVar();
		const dir = waveDirection( wave ).toVar();
		const phase = dot( dir, p0 ).mul( wave.k ).add( uTime.mul( wave.omega ) );
		const sinP = sin( phase );
		const cosP = cos( phase );

		const amplitude = uAmp.mul( wave.amplitude ).mul( lod );
		const horizontal = amplitude.mul( wave.steepness ).mul( uChoppy );

		displacement.addAssign( vec3(
			dir.x.mul( horizontal ).mul( cosP ),
			amplitude.mul( sinP ),
			dir.y.mul( horizontal ).mul( cosP )
		) );

	}

	return displacement;

} );

/**
 * Fragment-stage evaluation of the same five waves: the exact partial
 * derivatives of the Gerstner surface give a normal that never depends on
 * the tessellation, plus the horizontal Jacobian used for fold foam.
 * Returns vec4( normal.xyz, jacobian ).
 */
const gerstnerSurface = /*#__PURE__*/ Fn( ( [ p0, viewDistance ] ) => {

	const dPdx = vec3( 1.0, 0.0, 0.0 ).toVar();
	const dPdz = vec3( 0.0, 0.0, 1.0 ).toVar();

	for ( const wave of WAVE_TABLE ) {

		const lod = waveLod( wave, viewDistance ).toVar();
		const dir = waveDirection( wave ).toVar();
		const phase = dot( dir, p0 ).mul( wave.k ).add( uTime.mul( wave.omega ) );
		const sinP = sin( phase );
		const cosP = cos( phase );

		const amplitude = uAmp.mul( wave.amplitude );
		const horizontal = amplitude.mul( wave.steepness ).mul( uChoppy );

		const wa = amplitude.mul( wave.k ).mul( lod );        // k * A
		const wq = horizontal.mul( wave.k ).mul( lod );       // k * Q * A

		dPdx.addAssign( vec3(
			wq.mul( dir.x ).mul( dir.x ).mul( sinP ).negate(),
			wa.mul( dir.x ).mul( cosP ),
			wq.mul( dir.x ).mul( dir.y ).mul( sinP ).negate()
		) );

		dPdz.addAssign( vec3(
			wq.mul( dir.x ).mul( dir.y ).mul( sinP ).negate(),
			wa.mul( dir.y ).mul( cosP ),
			wq.mul( dir.y ).mul( dir.y ).mul( sinP ).negate()
		) );

	}

	const normal = normalize( cross( dPdz, dPdx ) );
	const jacobian = dPdx.x.mul( dPdz.z ).sub( dPdx.z.mul( dPdz.x ) );

	return vec4( normal, jacobian );

} );

// ==================================================================
// 5. One analytic sky — shared by the dome and by the water reflection
// ==================================================================

/**
 * @param {Node<vec3>} rayDir     normalised view/reflection direction
 * @param {Node<float>} diskGain  1 for the dome, 0 for reflections (the water
 *                                adds its own analytic sun specular instead)
 */
const skyRadiance = /*#__PURE__*/ Fn( ( [ rayDir, diskGain ] ) => {

	const dir = normalize( rayDir ).toVar();
	const height = dir.y.toVar();

	// vertical gradient, mirrored into a darker sea haze below the horizon
	const above = mix( uHorizonColor, uZenithColor, pow( clamp( height, 0.0, 1.0 ), 0.42 ) );
	const below = mix( uHorizonColor, uHorizonColor.mul( 0.28 ), clamp( height.negate().mul( 4.0 ), 0.0, 1.0 ) );
	const color = mix( below, above, step( 0.0, height ) ).toVar();

	const cosSun = clamp( dot( dir, uSunDir ), - 1.0, 1.0 ).toVar();
	const towardsSun = max( cosSun, 0.0 ).toVar();

	// aureole + wide forward scattering
	const halo = pow( towardsSun, 220.0 ).mul( 0.5 )
		.add( pow( towardsSun, 14.0 ).mul( 0.12 ) )
		.add( pow( towardsSun, 3.0 ).mul( 0.04 ) );

	color.addAssign( uSunColor.mul( halo ) );

	// clouds: FBM on the plane projection of the ray, faded out at the horizon
	const cloudFade = smoothstep( 0.015, 0.20, height ).mul( uCloudAmount );
	const cp = dir.xz.div( max( height, 0.055 ) ).mul( 0.55 ).add( uCloudDrift );
	const density = clamp( fbm( cp, 4, 0.012 ).add( uCloudCoverage ).mul( 2.1 ), 0.0, 1.0 ).toVar();
	const cloudColor = mix( uCloudDark, uCloudLight, smoothstep( 0.0, 0.85, density ) )
		.add( uSunColor.mul( pow( towardsSun, 6.0 ).mul( 0.18 ) ) );

	color.assign( mix( color, cloudColor, density.mul( cloudFade ) ) );

	// sun disk last, so clouds can occlude it
	const disk = smoothstep( 0.99965, 0.99992, cosSun ).mul( float( 1.0 ).sub( density.mul( cloudFade ) ) );
	color.addAssign( uSunColor.mul( disk ).mul( uSunDisk ).mul( diskGain ) );

	// horizon haze band keeps dome and water edge consistent
	const band = float( 1.0 ).sub( smoothstep( 0.0, 0.06, abs( height ) ) );
	color.assign( mix( color, uHorizonColor, band.mul( 0.22 ) ) );

	return color;

} );

// ==================================================================
// 6. Water surface shading
// ==================================================================

const waterColor = /*#__PURE__*/ Fn( () => {

	// positionGeometry is the *undisplaced* grid point: the node system turns
	// it into a varying automatically, which is exactly the domain the waves
	// were evaluated in — so the fragment stage can rebuild them exactly.
	const p0 = positionGeometry.xz.add( uDrift ).toVar();

	const worldPos = positionWorld.toVar();
	const toCamera = cameraPosition.sub( worldPos ).toVar();
	const distance = length( toCamera ).toVar();
	const viewDir = toCamera.div( distance ).toVar();

	const surface = gerstnerSurface( p0, distance ).toVar();
	const swellNormal = surface.xyz.toVar();
	const jacobian = surface.w.toVar();

	// distance-dependent detail fades keep the far field free of aliasing
	const rippleFade = float( 1.0 ).sub( smoothstep( 50.0, 620.0, distance ) ).mul( uDetail ).toVar();
	const microFade = float( 1.0 ).sub( smoothstep( 15.0, 190.0, distance ) ).mul( uDetail ).toVar();

	// gradient of the ripple field by central-ish differences
	const eps = 0.9;
	const h0 = rippleHeight( p0 ).toVar();
	const hx = rippleHeight( p0.add( vec2( eps, 0.0 ) ) );
	const hz = rippleHeight( p0.add( vec2( 0.0, eps ) ) );
	const rippleGrad = vec2( hx.sub( h0 ), hz.sub( h0 ) ).div( eps ).mul( uRipple.mul( 40.0 ) ).toVar();

	const epsC = 0.35;
	const c0 = capillaryHeight( p0 ).toVar();
	const cx = capillaryHeight( p0.add( vec2( epsC, 0.0 ) ) );
	const cz = capillaryHeight( p0.add( vec2( 0.0, epsC ) ) );
	const capGrad = vec2( cx.sub( c0 ), cz.sub( c0 ) ).div( epsC ).mul( uCapillary.mul( 12.0 ) ).toVar();

	const normal = normalize(
		swellNormal
			.add( vec3( rippleGrad.x.negate(), 0.0, rippleGrad.y.negate() ).mul( rippleFade ) )
			.add( vec3( capGrad.x.negate(), 0.0, capGrad.y.negate() ).mul( microFade ) )
	).toVar();

	// ---- Fresnel sky reflection -----------------------------------
	const nDotV = clamp( dot( normal, viewDir ), 0.001, 1.0 ).toVar();
	const fresnel = float( 0.02 ).add( float( 0.98 ).mul( pow( float( 1.0 ).sub( nDotV ), 5.0 ) ) ).toVar();

	const rawReflect = reflect( viewDir.negate(), normal );
	const reflectDir = normalize( vec3( rawReflect.x, max( rawReflect.y, 0.015 ), rawReflect.z ) );
	const reflection = skyRadiance( reflectDir, float( 0.0 ) ).mul( 0.92 ).toVar();

	// ---- volume / scattering --------------------------------------
	const ambient = uZenithColor.mul( 0.45 ).add( uHorizonColor.mul( 0.16 ) ).toVar();
	const sunUp = smoothstep( - 0.09, 0.07, uSunDir.y ).toVar();
	const crestRatio = clamp( worldPos.y.div( max( uAmp.mul( 3.4 ), 0.05 ) ).mul( 0.5 ).add( 0.5 ), 0.0, 1.0 ).toVar();

	const body = mix( uDeepColor, uShallowColor, crestRatio.mul( 0.7 ) )
		.mul( ambient.add( uSunColor.mul( sunUp.mul( 0.12 ) ) ) ).toVar();

	// backlit crests: light travelling through the wave towards the camera —
	// only on the faces turned away from the sun, and only high up the crest
	const backFacing = pow( clamp( float( 0.5 ).sub( dot( uSunDir, normal ).mul( 0.5 ) ), 0.0, 1.0 ), 3.0 );
	const backlit = pow( clamp( dot( viewDir, uSunDir.negate() ), 0.0, 1.0 ), 4.0 )
		.mul( backFacing.mul( 2.6 ) )
		.mul( clamp( worldPos.y.div( max( uAmp.mul( 2.6 ), 0.05 ) ), 0.0, 1.0 ) )
		.mul( sunUp );
	body.addAssign( uSssColor.mul( uSunColor ).mul( backlit.mul( uSss ) ) );

	// ---- sun glitter ----------------------------------------------
	const halfVec = normalize( viewDir.add( uSunDir ) );
	const nDotH = clamp( dot( normal, halfVec ), 0.0, 1.0 ).toVar();
	const sparkle = float( 0.55 ).add( clamp( abs( capGrad.x ).add( abs( capGrad.y ) ).mul( 2.2 ), 0.0, 1.0 ) );
	const specular = mix( pow( nDotH, 80.0 ).mul( 0.30 ), pow( nDotH, 900.0 ).mul( 2.4 ).mul( sparkle ), microFade )
		.mul( sunUp ).mul( uGlitter ).toVar();

	// ---- foam ------------------------------------------------------
	// The noise perturbs the *thresholds* rather than scaling the result, which
	// gives torn, organic patches instead of contour rings around the folds.
	const foamNoise = fbm( p0.mul( 0.55 ), 2, 0.35 ).toVar();
	const foamFold = smoothstep( 0.92, 0.58, jacobian.add( foamNoise.mul( 0.18 ) ) );
	const foamCrest = smoothstep( uCrestStart, uCrestEnd, worldPos.y.add( foamNoise.mul( uAmp ) ) );
	const foamBubbles = clamp( fbm( p0.mul( 1.3 ), 2, 0.25 ).mul( 1.6 ).add( 0.78 ), 0.22, 1.0 );
	const foamDistance = float( 1.0 ).sub( smoothstep( 260.0, 1300.0, distance ) );
	const foam = clamp( foamFold.mul( 1.2 ).add( foamCrest.mul( 0.32 ) ), 0.0, 1.0 )
		.mul( foamBubbles ).mul( uFoam ).mul( foamDistance ).toVar();
	const foamColor = ambient.mul( 1.35 ).add( uSunColor.mul( sunUp.mul( 0.42 ) ) );

	// ---- composition ----------------------------------------------
	const color = mix( body, reflection, fresnel ).toVar();
	color.addAssign( uSunColor.mul( specular ).mul( float( 1.0 ).sub( foam.mul( 0.8 ) ) ) );
	color.assign( mix( color, foamColor, foam ) );

	// ---- horizon haze: the water dissolves into the same sky -------
	const haze = float( 1.0 ).sub( exp( pow( distance.mul( 1.0 / 2200.0 ), 1.5 ).negate() ) );
	color.assign( mix( color, skyRadiance( viewDir.negate(), float( 0.0 ) ), haze ) );

	return vec4( color, 1.0 );

} );

// ==================================================================
// 7. Scene assembly
// ==================================================================

const state = {
	renderer: null,
	pipeline: null,
	scene: null,
	camera: null,
	controls: null,
	sky: null,
	ocean: null,
	running: false,
	paused: false,
	simTime: 0,
	lastFrame: 0,
	driftOffset: new Vector2( 0, 0 ),
	cloudOffset: new Vector2( 0, 0 ),
	seaState: 0.45,
	timeOfDay: 0.38,
	driftSpeed: reducedMotion.matches ? 0 : 0.35,
	timeScale: reducedMotion.matches ? 0.45 : 1,
	baseDpr: 1,
	dprScale: 1,
	frames: 0,
	fpsAccum: 0,
	fpsTimer: 0,
	slowSamples: 0,
	fastSamples: 0,
	firstFrameDone: false
};

/**
 * Ocean grid with a radially graded vertex density: dense where the camera
 * is, coarse towards the horizon, so 512² vertices go where they matter.
 */
function createOceanGeometry( halfSize, segments, falloff ) {

	const geometry = new PlaneGeometry( halfSize * 2, halfSize * 2, segments, segments );
	geometry.rotateX( - Math.PI / 2 );

	const position = geometry.attributes.position;

	for ( let i = 0; i < position.count; i ++ ) {

		const x = position.getX( i ) / halfSize;
		const z = position.getZ( i ) / halfSize;

		position.setX( i, Math.sign( x ) * Math.pow( Math.abs( x ), falloff ) * halfSize );
		position.setZ( i, Math.sign( z ) * Math.pow( Math.abs( z ), falloff ) * halfSize );

	}

	position.needsUpdate = true;
	geometry.computeBoundingSphere();

	return geometry;

}

// ---- time-of-day palette (JS side, shared through the uniforms) ----

const PALETTE = {
	zenithNight: new Color().setRGB( 0.006, 0.010, 0.026 ),
	zenithTwilight: new Color().setRGB( 0.050, 0.070, 0.170 ),
	zenithDay: new Color().setRGB( 0.045, 0.155, 0.480 ),

	horizonNight: new Color().setRGB( 0.018, 0.026, 0.048 ),
	horizonTwilight: new Color().setRGB( 0.720, 0.270, 0.110 ),
	horizonDay: new Color().setRGB( 0.440, 0.625, 0.875 ),

	sunMoon: new Color().setRGB( 0.34, 0.44, 0.78 ),
	sunLow: new Color().setRGB( 1.000, 0.360, 0.130 ),
	sunHigh: new Color().setRGB( 1.000, 0.955, 0.900 ),

	deepNight: new Color().setRGB( 0.0016, 0.0042, 0.0090 ),
	deepDay: new Color().setRGB( 0.0035, 0.0210, 0.0390 ),
	shallowNight: new Color().setRGB( 0.006, 0.030, 0.042 ),
	shallowDay: new Color().setRGB( 0.014, 0.086, 0.104 )
};

const _c0 = new Color();
const _c1 = new Color();

/** target = a * ka + b * kb  (Color has no addScaledVector). */
function blend( target, a, ka, b, kb ) {

	return target.setRGB(
		a.r * ka + b.r * kb,
		a.g * ka + b.g * kb,
		a.b * ka + b.b * kb
	);

}

function updateTimeOfDay( t ) {

	// t in [0,1] -> 04:00 .. 21:00
	const hour = 4 + t * 17;
	const dayPhase = ( hour - 5 ) / 14;             // 0 at sunrise, 1 at sunset
	const elevation = Math.sin( Math.PI * dayPhase ) * 1.22 - 0.09;
	const azimuth = Math.PI * 0.15 + dayPhase * Math.PI * 1.1;

	const sinE = Math.sin( elevation );
	const day = MathUtils.smoothstep( sinE, 0.06, 0.34 );
	const civil = MathUtils.smoothstep( sinE, - 0.25, 0.06 );
	const twilight = civil * ( 1 - day );

	uSunDir.value.set(
		Math.cos( elevation ) * Math.sin( azimuth ),
		sinE,
		Math.cos( elevation ) * Math.cos( azimuth )
	).normalize();

	// sun / moon colour and intensity
	_c0.copy( PALETTE.sunLow ).lerp( PALETTE.sunHigh, day );
	_c0.lerp( PALETTE.sunMoon, ( 1 - civil ) * 0.85 );
	const intensity = 0.22 + civil * 1.7 + day * 3.7;
	uSunColor.value.copy( _c0 ).multiplyScalar( intensity );
	uSunDisk.value = 6 + day * 20;

	// sky gradient
	_c0.copy( PALETTE.zenithNight ).lerp( PALETTE.zenithDay, day ).lerp( PALETTE.zenithTwilight, twilight * 0.85 );
	uZenithColor.value.copy( _c0 );

	_c1.copy( PALETTE.horizonNight ).lerp( PALETTE.horizonDay, day ).lerp( PALETTE.horizonTwilight, twilight * 0.9 );
	uHorizonColor.value.copy( _c1 );

	// clouds pick up the sun tint, shadow side stays in the sky gradient
	blend( uCloudLight.value, _c1, 0.32, uSunColor.value, 0.20 + day * 0.10 );
	blend( uCloudDark.value, _c0, 1.30, _c1, 0.14 );

	// water body colours
	uDeepColor.value.copy( PALETTE.deepNight ).lerp( PALETTE.deepDay, day );
	uShallowColor.value.copy( PALETTE.shallowNight ).lerp( PALETTE.shallowDay, day );
	uSss.value = 0.35 + day * 0.85;
	uGlitter.value = 0.35 + civil * 0.9;

	if ( state.renderer !== null ) {

		state.renderer.toneMappingExposure = 1.0 + ( 1 - civil ) * 0.45;

	}

	return hour;

}

const SEA_LABELS = [
	'0 — spiegelglatt', '1 — ruhig, gekräuselt', '2 — leicht bewegt', '3 — schwach bewegt',
	'4 — mäßig bewegt', '5 — grob', '6 — sehr grob', '7 — hoch'
];

function updateSeaState( s ) {

	const amp = 0.18 + s * 1.45;

	uAmp.value = amp;
	uChoppy.value = 0.25 + s * 0.95;
	uRipple.value = 0.030 + s * 0.115;
	uCapillary.value = 0.018 + s * 0.042;
	uFoam.value = Math.pow( s, 1.7 ) * 1.05;
	uCloudCoverage.value = 0.02 + s * 0.20;
	uCloudAmount.value = 0.65 + s * 0.30;

	// crest foam band follows the actual wave height (Σ amplitudes × scale)
	const maxHeight = 6.7 * amp;
	uCrestStart.value = maxHeight * 0.66;
	uCrestEnd.value = maxHeight * 0.95;

	// wind bearing turns slightly with the sea state
	const bearing = 0.45 + s * 0.9;
	uWindDir.value.set( Math.cos( bearing ), Math.sin( bearing ) );

	return SEA_LABELS[ Math.min( SEA_LABELS.length - 1, Math.round( s * 7 ) ) ];

}

async function init() {

	const probe = await detectWebGPU();

	if ( probe.ok !== true ) {

		fail( probe.title, null, probe.detail );
		return;

	}

	const scene = new Scene();

	const camera = new PerspectiveCamera( 52, window.innerWidth / window.innerHeight, 1, 30000 );
	camera.position.set( 0, 22, 132 );

	const renderer = new WebGPURenderer( {
		canvas: dom.canvas,
		antialias: false,       // resolved by the post pipeline / DPR instead
		alpha: false
	} );

	state.baseDpr = Math.min( window.devicePixelRatio || 1, isCoarsePointer ? 1.75 : 2 );
	renderer.setPixelRatio( state.baseDpr );
	renderer.setSize( window.innerWidth, window.innerHeight, false );
	renderer.toneMapping = ACESFilmicToneMapping;
	renderer.toneMappingExposure = 1.0;

	try {

		await renderer.init();

	} catch ( err ) {

		fail( 'WebGPU-Initialisierung fehlgeschlagen', null, err.message );
		return;

	}

	// Refuse the silent WebGL2 fallback of WebGPURenderer.
	if ( renderer.backend === undefined || renderer.backend.isWebGPUBackend !== true ) {

		renderer.dispose();
		fail(
			'WebGPU-Backend nicht aktiv',
			'Der Renderer wäre auf WebGL2 zurückgefallen. Diese Szene rendert bewusst nur mit WebGPU.',
			'renderer.backend.isWebGPUBackend !== true'
		);
		return;

	}

	const device = renderer.backend.device;

	if ( device !== undefined && device !== null && device.lost !== undefined ) {

		device.lost.then( ( info ) => {

			if ( info.reason === 'destroyed' ) return; // normal teardown
			state.running = false;
			renderer.setAnimationLoop( null );
			fail( 'GPU-Verbindung verloren', 'Das WebGPU-Gerät wurde vom System zurückgesetzt. Bitte die Seite neu laden.', info.message );

		} );

	}

	// ---- sky dome ---------------------------------------------------
	const skyMaterial = new MeshBasicNodeMaterial();
	skyMaterial.side = BackSide;
	skyMaterial.depthWrite = false;
	skyMaterial.depthTest = false;
	skyMaterial.fog = false;
	skyMaterial.colorNode = vec4( skyRadiance( normalize( positionWorld.sub( cameraPosition ) ), float( 1.0 ) ), 1.0 );

	const sky = new Mesh( new SphereGeometry( 4000, 48, 32 ), skyMaterial );
	sky.frustumCulled = false;
	sky.renderOrder = - 1;
	scene.add( sky );

	// ---- ocean ------------------------------------------------------
	const segments = isCoarsePointer ? 320 : 512;
	const oceanMaterial = new MeshBasicNodeMaterial();
	oceanMaterial.positionNode = positionGeometry.add( gerstnerDisplacement(
		positionGeometry.xz.add( uDrift ),
		length( cameraPosition.sub( positionGeometry ) )
	) );
	oceanMaterial.colorNode = waterColor();
	oceanMaterial.fog = false;

	const ocean = new Mesh( createOceanGeometry( 9000, segments, 1.6 ), oceanMaterial );
	ocean.frustumCulled = false;
	scene.add( ocean );

	// ---- controls ---------------------------------------------------
	const controls = new OrbitControls( camera, renderer.domElement );
	controls.enableDamping = true;
	controls.dampingFactor = 0.055;
	controls.enablePan = false;
	controls.minDistance = 20;
	controls.maxDistance = 520;
	controls.minPolarAngle = 0.15;
	controls.maxPolarAngle = Math.PI * 0.497;   // never dive under the surface
	controls.rotateSpeed = 0.55;
	controls.zoomSpeed = 0.8;
	controls.target.set( 0, 12, 0 );
	controls.autoRotate = ! reducedMotion.matches;
	controls.autoRotateSpeed = 0.06;
	controls.update();

	// ---- post processing: TSL bloom + ACES ---------------------------
	const scenePass = pass( scene, camera );
	const bloomPass = bloom( scenePass, 0.42, 0.55, 0.72 );

	const pipeline = new RenderPipeline( renderer );
	pipeline.outputNode = vec4( scenePass.rgb.add( bloomPass.rgb ), 1.0 );

	Object.assign( state, { renderer, pipeline, scene, camera, controls, sky, ocean } );

	// initial parameter push
	applySeaState( Number( dom.sea.value ) / 100 );
	applyTimeOfDay( Number( dom.time.value ) / 100 );
	applyDrift( reducedMotion.matches ? 0 : Number( dom.drift.value ) / 100 );

	try {

		await renderer.compileAsync( scene, camera );

	} catch ( err ) {

		fail( 'Shader-Übersetzung fehlgeschlagen', null, err.message );
		return;

	}

	bindUI();
	window.addEventListener( 'resize', onResize, { passive: true } );
	window.addEventListener( 'orientationchange', onResize, { passive: true } );
	document.addEventListener( 'visibilitychange', onVisibility );

	state.lastFrame = performance.now();
	state.running = true;
	renderer.setAnimationLoop( animate );

}

// ==================================================================
// 8. Frame loop, performance safeguards, lifecycle
// ==================================================================

function animate() {

	const now = performance.now();
	let delta = ( now - state.lastFrame ) / 1000;
	state.lastFrame = now;

	// clamp the step so a stalled tab or a slow frame cannot jump the sea
	delta = Math.min( delta, 1 / 15 );

	if ( state.paused === false ) {

		state.simTime += delta * state.timeScale;
		uTime.value = state.simTime;

		const wind = uWindDir.value;
		const speed = state.driftSpeed * 9.0;
		state.driftOffset.x += wind.x * speed * delta;
		state.driftOffset.y += wind.y * speed * delta;
		uDrift.value.copy( state.driftOffset );

		state.cloudOffset.x += wind.x * state.driftSpeed * 0.028 * delta;
		state.cloudOffset.y += wind.y * state.driftSpeed * 0.028 * delta;
		uCloudDrift.value.copy( state.cloudOffset );

	}

	state.controls.update( delta );
	state.sky.position.copy( state.camera.position );

	state.pipeline.render();

	if ( state.firstFrameDone === false ) {

		state.firstFrameDone = true;
		dom.loading.hidden = true;   // focus is left where the user put it

	}

	measurePerformance( delta );

}

function measurePerformance( delta ) {

	state.frames ++;
	state.fpsAccum += delta;

	if ( state.fpsAccum < 0.5 ) return;

	const fps = state.frames / state.fpsAccum;
	state.frames = 0;
	state.fpsAccum = 0;

	dom.fps.textContent = fps.toFixed( 0 );
	dom.fpsLive.textContent = `${ fps.toFixed( 0 ) } Bilder pro Sekunde`;

	// adaptive resolution: give up pixels before giving up smoothness
	if ( fps < 40 && state.dprScale > 0.6 ) {

		state.slowSamples ++;
		state.fastSamples = 0;

		if ( state.slowSamples >= 3 ) {

			state.slowSamples = 0;
			setResolutionScale( Math.max( 0.6, state.dprScale - 0.15 ) );

		}

	} else if ( fps > 57 && state.dprScale < 1 ) {

		state.fastSamples ++;
		state.slowSamples = 0;

		if ( state.fastSamples >= 8 ) {

			state.fastSamples = 0;
			setResolutionScale( Math.min( 1, state.dprScale + 0.1 ) );

		}

	} else {

		state.slowSamples = 0;
		state.fastSamples = 0;

	}

}

function setResolutionScale( scale ) {

	state.dprScale = scale;
	const dpr = Math.max( 0.5, state.baseDpr * scale );
	state.renderer.setPixelRatio( dpr );
	state.renderer.setSize( window.innerWidth, window.innerHeight, false );
	dom.quality.textContent = `DPR ${ dpr.toFixed( 2 ) }`;

}

let resizeHandle = 0;

function onResize() {

	if ( resizeHandle !== 0 ) cancelAnimationFrame( resizeHandle );

	resizeHandle = requestAnimationFrame( () => {

		resizeHandle = 0;

		if ( state.renderer === null ) return;

		const width = window.innerWidth;
		const height = window.innerHeight;

		state.camera.aspect = width / height;
		state.camera.updateProjectionMatrix();
		state.renderer.setSize( width, height, false );

	} );

}

function onVisibility() {

	if ( state.renderer === null || state.running === false ) return;

	if ( document.hidden ) {

		state.renderer.setAnimationLoop( null );

	} else {

		state.lastFrame = performance.now();
		state.renderer.setAnimationLoop( animate );

	}

}

// ==================================================================
// 9. UI wiring
// ==================================================================

function applySeaState( value ) {

	state.seaState = value;
	dom.seaOut.textContent = updateSeaState( value );

}

function applyTimeOfDay( value ) {

	state.timeOfDay = value;
	const hour = updateTimeOfDay( value );
	const h = Math.floor( hour );
	const m = Math.floor( ( hour - h ) * 60 );
	dom.timeOut.textContent = `${ String( h ).padStart( 2, '0' ) }:${ String( m ).padStart( 2, '0' ) }`;

}

function applyDrift( value ) {

	state.driftSpeed = value;
	dom.driftOut.textContent = `${ value.toFixed( 2 ) }×`;

	if ( state.controls !== null ) {

		state.controls.autoRotate = value > 0 && ! reducedMotion.matches;
		state.controls.autoRotateSpeed = 0.03 + value * 0.12;

	}

}

function setPanelVisible( visible ) {

	dom.panel.hidden = ! visible;
	dom.panelOpen.hidden = visible;
	dom.panelOpen.setAttribute( 'aria-expanded', String( visible ) );

	if ( visible ) dom.panelClose.focus();
	else dom.panelOpen.focus();

}

function setPaused( paused ) {

	state.paused = paused;
	dom.pause.textContent = paused ? 'Weiter' : 'Pause';
	dom.pause.setAttribute( 'aria-pressed', String( paused ) );

}

function bindUI() {

	dom.sea.addEventListener( 'input', () => applySeaState( Number( dom.sea.value ) / 100 ) );
	dom.time.addEventListener( 'input', () => applyTimeOfDay( Number( dom.time.value ) / 100 ) );
	dom.drift.addEventListener( 'input', () => applyDrift( Number( dom.drift.value ) / 100 ) );

	dom.pause.addEventListener( 'click', () => setPaused( ! state.paused ) );
	dom.panelClose.addEventListener( 'click', () => setPanelVisible( false ) );
	dom.panelOpen.addEventListener( 'click', () => setPanelVisible( true ) );

	if ( reducedMotion.matches ) {

		dom.drift.value = '0';
		applyDrift( 0 );

	}

	reducedMotion.addEventListener( 'change', ( event ) => {

		state.timeScale = event.matches ? 0.45 : 1;
		applyDrift( event.matches ? 0 : Number( dom.drift.value ) / 100 );

	} );

	window.addEventListener( 'keydown', ( event ) => {

		if ( event.metaKey || event.ctrlKey || event.altKey ) return;

		// never shadow the activation keys of a focused control
		const onControl = event.target instanceof Element &&
			event.target.closest( 'button, input, select, textarea, a, summary' ) !== null;

		if ( event.key === 'h' || event.key === 'H' ) {

			if ( onControl && event.target.matches( 'input, textarea' ) ) return;
			setPanelVisible( dom.panel.hidden );

		} else if ( event.key === 'p' || event.key === 'P' || ( event.code === 'Space' && ! onControl ) ) {

			if ( event.target instanceof Element && event.target.matches( 'input, textarea' ) ) return;
			event.preventDefault();
			setPaused( ! state.paused );

		}

	} );

	setResolutionScale( 1 );

}

// ==================================================================
// 10. Boot
// ==================================================================

init().catch( ( err ) => {

	console.error( err );
	fail( 'Unerwarteter Fehler beim Start', null, err && err.message ? err.message : String( err ) );

} );
