/* Countdown to the next event as a flip clock (#flipClock[data-target]) on the home page (home/index). */
class FlipClock {
    constructor(element, targetDate) {
        this.element = element;
        this.targetDate = new Date(targetDate).getTime();
        this.units = ['days', 'hours', 'minutes', 'seconds'];
        this.cards = {};
        this.interval = null;
        this.init();
    }

    init() {
        this.element.innerHTML = '';
        this.units.forEach((unit, index) => {
            const unitEl = document.createElement('div');
            unitEl.className = 'flip-unit';
            
            const cardEl = document.createElement('div');
            cardEl.className = 'flip-card';
            cardEl.innerHTML = `
                <div class="flip-card-inner">
                    <div class="flip-card-front">00</div>
                    <div class="flip-card-back">00</div>
                </div>
            `;
            
            const labelEl = document.createElement('div');
            labelEl.className = 'flip-label';
            labelEl.textContent = unit.charAt(0).toUpperCase() + unit.slice(1);
            
            unitEl.appendChild(cardEl);
            unitEl.appendChild(labelEl);
            this.element.appendChild(unitEl);
            
            this.cards[unit] = {
                front: cardEl.querySelector('.flip-card-front'),
                back: cardEl.querySelector('.flip-card-back'),
                inner: cardEl.querySelector('.flip-card-inner'),
                value: '00'
            };
            
            if (index < this.units.length - 1) {
                const sep = document.createElement('div');
                sep.className = 'flip-separator';
                sep.textContent = ':';
                this.element.appendChild(sep);
            }
        });
        
        this.update();
        this.interval = setInterval(() => this.update(), 1000);
    }

    update() {
        const now = Date.now();
        const diff = this.targetDate - now;
        
        if (diff <= 0) {
            this.expire();
            return;
        }
        
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);
        
        this.setValue('days', days.toString().padStart(2, '0'));
        this.setValue('hours', hours.toString().padStart(2, '0'));
        this.setValue('minutes', minutes.toString().padStart(2, '0'));
        this.setValue('seconds', seconds.toString().padStart(2, '0'));
    }

    setValue(unit, value) {
        const card = this.cards[unit];
        if (card.value !== value) {
            card.back.textContent = value;
            card.inner.classList.add('flip');
            
            setTimeout(() => {
                card.front.textContent = value;
                card.inner.classList.remove('flip');
                card.value = value;
            }, 300);
        }
    }

    expire() {
        clearInterval(this.interval);
        this.element.classList.add('expired');
        this.units.forEach(unit => {
            this.cards[unit].front.textContent = '00';
            this.cards[unit].back.textContent = '00';
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const clockEl = document.getElementById('flipClock');
    if (clockEl) {
        const targetDateStr = clockEl.dataset.target;
        if (targetDateStr) {
            // Parse as local time (no Z or timezone offset)
            const targetDate = new Date(targetDateStr).getTime();
            if (!isNaN(targetDate)) {
                new FlipClock(clockEl, targetDate);
            } else {
                console.error('Invalid target date:', targetDateStr);
            }
        }
    }
});
