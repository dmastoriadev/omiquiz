import './bootstrap';
import Alpine from 'alpinejs';
import registerQuiz from './quiz';
import registerSketch from './sketch';
import registerSlots from './slots';

window.Alpine = Alpine;
registerQuiz(Alpine);
registerSketch(Alpine);
registerSlots(Alpine);
Alpine.start();
