/**
 * POS entry point kept separate from index.js so we can evolve the shell
 * (e.g. swap to Vue/Inertia) without touching the legacy imperative logic.
 * For now we simply import the existing implementation.
 */
import './index.js';
