import './bootstrap';
import Alpine from 'alpinejs';
import registerQuiz from './quiz';

window.Alpine = Alpine;
registerQuiz(Alpine);
Alpine.start();
