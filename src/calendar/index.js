import { initBciCalendarRuntime } from './runtime.js';

if ( 'undefined' !== typeof window && window.document ) {
	initBciCalendarRuntime( window );
}
