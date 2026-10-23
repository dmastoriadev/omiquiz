import './bootstrap';
import Alpine from 'alpinejs';
import registerQuiz from './quiz';
import registerSketch from './sketch';

window.Alpine = Alpine;
registerQuiz(Alpine);
registerSketch(Alpine);
Alpine.start();
