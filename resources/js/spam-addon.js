import SpamReviewIndex from './components/SpamReviewIndex.vue';
import SpamReviewShow from "./components/SpamReviewShow.vue";

Statamic.booting(() => {
    Statamic.component('spam-review-index', SpamReviewIndex);
    Statamic.component('spam-review-show', SpamReviewShow);
});
