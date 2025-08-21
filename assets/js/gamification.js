/**
 * WCEventsFP Gamification System
 * Loyalty points, badges, achievements, and user engagement features
 */

class WCEFPGamification {
    constructor() {
        this.apiEndpoint = window.wcefp_ajax?.url || '/wp-admin/admin-ajax.php';
        this.nonce = window.wcefp_ajax?.nonce || '';
        this.userPoints = 0;
        this.userLevel = 1;
        this.userBadges = [];
        this.achievements = [];
        this.init();
    }

    init() {
        this.loadUserData();
        this.createGamificationUI();
        this.bindEvents();
        this.trackUserActions();
        this.setupLevelSystem();
        this.initializeNotifications();
    }

    async loadUserData() {
        try {
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'wcefp_get_user_gamification_data',
                    nonce: this.nonce
                })
            });

            const data = await response.json();
            if (data.success) {
                this.userPoints = data.data.points || 0;
                this.userLevel = data.data.level || 1;
                this.userBadges = data.data.badges || [];
                this.achievements = data.data.achievements || [];
                this.updateUI();
            }
        } catch (error) {
            console.error('Failed to load gamification data:', error);
        }
    }

    createGamificationUI() {
        // Create points widget
        this.createPointsWidget();
        
        // Create progress bar
        this.createProgressBar();
        
        // Create achievements panel
        this.createAchievementsPanel();
        
        // Create badges collection
        this.createBadgesCollection();
        
        // Create leaderboard
        this.createLeaderboard();
    }

    createPointsWidget() {
        const existingWidget = document.querySelector('.wcefp-points-widget');
        if (existingWidget) return;

        const widget = document.createElement('div');
        widget.className = 'wcefp-points-widget';
        widget.innerHTML = `
            <div class="wcefp-points-container">
                <div class="wcefp-points-icon">⭐</div>
                <div class="wcefp-points-info">
                    <div class="wcefp-points-value" id="wcefp-user-points">${this.userPoints}</div>
                    <div class="wcefp-points-label">Punti</div>
                </div>
                <div class="wcefp-level-info">
                    <div class="wcefp-level-badge" id="wcefp-user-level">Lv. ${this.userLevel}</div>
                </div>
            </div>
        `;

        // Insert widget in appropriate location
        const insertTarget = document.querySelector('.wcefp-booking-widget, .wcefp-event-grid, .site-header');
        if (insertTarget) {
            insertTarget.parentNode.insertBefore(widget, insertTarget);
        }
    }

    createProgressBar() {
        const progressContainer = document.createElement('div');
        progressContainer.className = 'wcefp-level-progress-container';
        progressContainer.innerHTML = `
            <div class="wcefp-level-progress-header">
                <span>Livello ${this.userLevel}</span>
                <span id="wcefp-progress-text">${this.getProgressToNextLevel()}%</span>
            </div>
            <div class="wcefp-level-progress-bar">
                <div class="wcefp-level-progress-fill" id="wcefp-progress-fill" style="width: ${this.getProgressToNextLevel()}%"></div>
            </div>
            <div class="wcefp-level-progress-footer">
                <span>${this.getPointsForCurrentLevel()} punti</span>
                <span>${this.getPointsForNextLevel()} punti</span>
            </div>
        `;

        const pointsWidget = document.querySelector('.wcefp-points-widget');
        if (pointsWidget) {
            pointsWidget.appendChild(progressContainer);
        }
    }

    createAchievementsPanel() {
        const panel = document.createElement('div');
        panel.className = 'wcefp-achievements-panel';
        panel.innerHTML = `
            <div class="wcefp-achievements-header">
                <h3>🏆 Achievements</h3>
                <button class="wcefp-achievements-toggle" id="wcefp-achievements-toggle">
                    <span class="wcefp-achievements-count">${this.achievements.length}</span>
                </button>
            </div>
            <div class="wcefp-achievements-content" id="wcefp-achievements-content">
                <div class="wcefp-achievements-grid" id="wcefp-achievements-grid">
                    ${this.renderAchievements()}
                </div>
            </div>
        `;

        document.body.appendChild(panel);
    }

    createBadgesCollection() {
        const collection = document.createElement('div');
        collection.className = 'wcefp-badges-collection';
        collection.innerHTML = `
            <div class="wcefp-badges-header">
                <h3>🎖️ Badge Collection</h3>
                <div class="wcefp-badges-stats">
                    <span>${this.userBadges.length} / ${this.getTotalBadgesCount()} Badge</span>
                </div>
            </div>
            <div class="wcefp-badges-grid" id="wcefp-badges-grid">
                ${this.renderBadges()}
            </div>
        `;

        const achievementsPanel = document.querySelector('.wcefp-achievements-panel');
        if (achievementsPanel) {
            achievementsPanel.appendChild(collection);
        }
    }

    createLeaderboard() {
        const leaderboard = document.createElement('div');
        leaderboard.className = 'wcefp-leaderboard';
        leaderboard.innerHTML = `
            <div class="wcefp-leaderboard-header">
                <h3>🥇 Classifica</h3>
                <select class="wcefp-leaderboard-period" id="wcefp-leaderboard-period">
                    <option value="weekly">Settimana</option>
                    <option value="monthly">Mese</option>
                    <option value="all-time">Sempre</option>
                </select>
            </div>
            <div class="wcefp-leaderboard-content" id="wcefp-leaderboard-content">
                <div class="wcefp-loading">Caricamento classifica...</div>
            </div>
        `;

        const badgesCollection = document.querySelector('.wcefp-badges-collection');
        if (badgesCollection) {
            badgesCollection.parentNode.appendChild(leaderboard);
        }
    }

    renderAchievements() {
        const allAchievements = this.getAllAchievements();
        return allAchievements.map(achievement => {
            const isUnlocked = this.achievements.some(a => a.id === achievement.id);
            return `
                <div class="wcefp-achievement ${isUnlocked ? 'unlocked' : 'locked'}" 
                     data-achievement-id="${achievement.id}">
                    <div class="wcefp-achievement-icon">${achievement.icon}</div>
                    <div class="wcefp-achievement-info">
                        <div class="wcefp-achievement-title">${achievement.title}</div>
                        <div class="wcefp-achievement-description">${achievement.description}</div>
                        ${isUnlocked ? `<div class="wcefp-achievement-date">Sbloccato il ${this.formatDate(achievement.unlockedAt)}</div>` : ''}
                        <div class="wcefp-achievement-points">+${achievement.points} punti</div>
                    </div>
                    ${!isUnlocked ? '<div class="wcefp-achievement-lock">🔒</div>' : ''}
                </div>
            `;
        }).join('');
    }

    renderBadges() {
        const allBadges = this.getAllBadges();
        return allBadges.map(badge => {
            const isOwned = this.userBadges.some(b => b.id === badge.id);
            return `
                <div class="wcefp-badge ${isOwned ? 'owned' : 'not-owned'}" 
                     data-badge-id="${badge.id}">
                    <div class="wcefp-badge-icon">${badge.icon}</div>
                    <div class="wcefp-badge-info">
                        <div class="wcefp-badge-title">${badge.title}</div>
                        <div class="wcefp-badge-description">${badge.description}</div>
                        ${isOwned ? `<div class="wcefp-badge-earned">Ottenuto il ${this.formatDate(badge.earnedAt)}</div>` : ''}
                    </div>
                    ${!isOwned ? '<div class="wcefp-badge-lock">🔒</div>' : ''}
                </div>
            `;
        }).join('');
    }

    bindEvents() {
        // Toggle achievements panel
        const achievementsToggle = document.getElementById('wcefp-achievements-toggle');
        if (achievementsToggle) {
            achievementsToggle.addEventListener('click', () => {
                this.toggleAchievementsPanel();
            });
        }

        // Leaderboard period change
        const leaderboardPeriod = document.getElementById('wcefp-leaderboard-period');
        if (leaderboardPeriod) {
            leaderboardPeriod.addEventListener('change', (e) => {
                this.loadLeaderboard(e.target.value);
            });
        }

        // Achievement hover effects
        document.addEventListener('mouseenter', (e) => {
            if (e.target.closest('.wcefp-achievement')) {
                this.showAchievementTooltip(e.target.closest('.wcefp-achievement'));
            }
        }, true);

        // Badge click events
        document.addEventListener('click', (e) => {
            if (e.target.closest('.wcefp-badge.owned')) {
                this.showBadgeDetails(e.target.closest('.wcefp-badge'));
            }
        });
    }

    trackUserActions() {
        // Track booking completions
        document.addEventListener('wcefp-booking-completed', (e) => {
            this.awardPoints('booking_completed', e.detail);
        });

        // Track event views
        document.addEventListener('wcefp-event-viewed', (e) => {
            this.awardPoints('event_viewed', e.detail);
        });

        // Track social sharing
        document.addEventListener('wcefp-event-shared', (e) => {
            this.awardPoints('event_shared', e.detail);
        });

        // Track review submissions
        document.addEventListener('wcefp-review-submitted', (e) => {
            this.awardPoints('review_submitted', e.detail);
        });

        // Track consecutive bookings
        this.trackConsecutiveBookings();

        // Track seasonal activities
        this.trackSeasonalActivities();
    }

    async awardPoints(action, data = {}) {
        const pointsToAward = this.getPointsForAction(action, data);
        if (pointsToAward <= 0) return;

        try {
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'wcefp_award_points',
                    nonce: this.nonce,
                    points: pointsToAward,
                    action_type: action,
                    action_data: JSON.stringify(data)
                })
            });

            const result = await response.json();
            if (result.success) {
                this.updatePoints(result.data.total_points);
                this.checkLevelUp(result.data.old_level, result.data.new_level);
                this.checkAchievements(result.data.achievements);
                this.checkBadges(result.data.badges);
            }
        } catch (error) {
            console.error('Failed to award points:', error);
        }
    }

    getPointsForAction(action, data) {
        const pointsMap = {
            'event_viewed': 1,
            'event_shared': 5,
            'booking_completed': 10,
            'review_submitted': 8,
            'profile_completed': 15,
            'first_booking': 20,
            'referral_successful': 25,
            'seasonal_event': 12,
            'consecutive_booking': 15
        };

        let basePoints = pointsMap[action] || 0;

        // Bonus multipliers
        if (action === 'booking_completed') {
            // Higher value bookings get more points
            if (data.booking_value > 100) basePoints *= 1.5;
            if (data.booking_value > 200) basePoints *= 2;
            
            // Group bookings get bonus points
            if (data.group_size > 2) basePoints += data.group_size;
        }

        return Math.round(basePoints);
    }

    updatePoints(newTotal) {
        const oldPoints = this.userPoints;
        this.userPoints = newTotal;
        
        // Animate points counter
        this.animatePointsCounter(oldPoints, newTotal);
        
        // Show points notification
        this.showPointsNotification(newTotal - oldPoints);
    }

    animatePointsCounter(from, to) {
        const pointsElement = document.getElementById('wcefp-user-points');
        if (!pointsElement) return;

        const duration = 1000;
        const startTime = performance.now();

        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            const current = from + (to - from) * this.easeOutCubic(progress);
            pointsElement.textContent = Math.round(current);
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        };
        
        requestAnimationFrame(animate);
    }

    checkLevelUp(oldLevel, newLevel) {
        if (newLevel > oldLevel) {
            this.userLevel = newLevel;
            this.showLevelUpNotification(newLevel);
            this.updateLevelDisplay();
            this.triggerLevelUpEffects();
        }
    }

    checkAchievements(newAchievements) {
        if (!newAchievements || newAchievements.length === 0) return;

        newAchievements.forEach(achievement => {
            this.achievements.push(achievement);
            this.showAchievementUnlockedNotification(achievement);
        });

        this.updateAchievementsDisplay();
    }

    checkBadges(newBadges) {
        if (!newBadges || newBadges.length === 0) return;

        newBadges.forEach(badge => {
            this.userBadges.push(badge);
            this.showBadgeEarnedNotification(badge);
        });

        this.updateBadgesDisplay();
    }

    showPointsNotification(points) {
        if (points <= 0) return;

        const notification = document.createElement('div');
        notification.className = 'wcefp-points-notification';
        notification.innerHTML = `
            <div class="wcefp-points-notification-icon">⭐</div>
            <div class="wcefp-points-notification-text">+${points} punti!</div>
        `;

        document.body.appendChild(notification);

        // Position near points widget
        const pointsWidget = document.querySelector('.wcefp-points-widget');
        if (pointsWidget) {
            const rect = pointsWidget.getBoundingClientRect();
            notification.style.position = 'fixed';
            notification.style.top = `${rect.top - 60}px`;
            notification.style.left = `${rect.left + rect.width / 2}px`;
            notification.style.transform = 'translateX(-50%)';
        }

        // Animate and remove
        setTimeout(() => {
            notification.classList.add('wcefp-show');
        }, 100);

        setTimeout(() => {
            notification.classList.add('wcefp-fade-out');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 3000);
    }

    showLevelUpNotification(newLevel) {
        const notification = document.createElement('div');
        notification.className = 'wcefp-level-up-notification';
        notification.innerHTML = `
            <div class="wcefp-level-up-content">
                <div class="wcefp-level-up-icon">🎉</div>
                <div class="wcefp-level-up-title">Livello Superiore!</div>
                <div class="wcefp-level-up-level">Livello ${newLevel}</div>
                <div class="wcefp-level-up-rewards">Nuove ricompense sbloccate!</div>
                <button class="wcefp-level-up-close">Fantastico!</button>
            </div>
        `;

        document.body.appendChild(notification);

        // Bind close button
        notification.querySelector('.wcefp-level-up-close').addEventListener('click', () => {
            notification.remove();
        });

        // Auto-remove after 10 seconds
        setTimeout(() => {
            if (document.body.contains(notification)) {
                notification.remove();
            }
        }, 10000);
    }

    showAchievementUnlockedNotification(achievement) {
        const notification = document.createElement('div');
        notification.className = 'wcefp-achievement-notification';
        notification.innerHTML = `
            <div class="wcefp-achievement-notification-content">
                <div class="wcefp-achievement-notification-icon">${achievement.icon}</div>
                <div class="wcefp-achievement-notification-text">
                    <div class="wcefp-achievement-notification-title">Achievement Sbloccato!</div>
                    <div class="wcefp-achievement-notification-name">${achievement.title}</div>
                    <div class="wcefp-achievement-notification-points">+${achievement.points} punti</div>
                </div>
            </div>
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.classList.add('wcefp-show');
        }, 100);

        setTimeout(() => {
            notification.classList.add('wcefp-fade-out');
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 5000);
    }

    setupLevelSystem() {
        // Level thresholds
        this.levelThresholds = [
            0, 100, 250, 500, 1000, 1800, 3000, 4500, 6500, 9000, 12000, 16000, 21000, 27000, 34000, 42000, 52000, 64000, 78000, 95000
        ];
    }

    getPointsForCurrentLevel() {
        return this.levelThresholds[this.userLevel - 1] || 0;
    }

    getPointsForNextLevel() {
        return this.levelThresholds[this.userLevel] || this.levelThresholds[this.levelThresholds.length - 1];
    }

    getProgressToNextLevel() {
        const currentLevelPoints = this.getPointsForCurrentLevel();
        const nextLevelPoints = this.getPointsForNextLevel();
        const pointsInCurrentLevel = this.userPoints - currentLevelPoints;
        const pointsNeededForNextLevel = nextLevelPoints - currentLevelPoints;
        
        return Math.min(100, (pointsInCurrentLevel / pointsNeededForNextLevel) * 100);
    }

    getAllAchievements() {
        return [
            {
                id: 'first_booking',
                title: 'Prima Esperienza',
                description: 'Completa la tua prima prenotazione',
                icon: '🎟️',
                points: 20
            },
            {
                id: 'early_bird',
                title: 'Early Bird',
                description: 'Prenota un evento con più di 7 giorni di anticipo',
                icon: '🐦',
                points: 10
            },
            {
                id: 'social_butterfly',
                title: 'Social Butterfly',
                description: 'Condividi 5 eventi sui social media',
                icon: '🦋',
                points: 25
            },
            {
                id: 'reviewer',
                title: 'Critico Esperto',
                description: 'Scrivi 10 recensioni',
                icon: '⭐',
                points: 50
            },
            {
                id: 'explorer',
                title: 'Esploratore',
                description: 'Prenota eventi in 5 città diverse',
                icon: '🗺️',
                points: 75
            },
            {
                id: 'group_leader',
                title: 'Leader del Gruppo',
                description: 'Organizza una prenotazione per più di 8 persone',
                icon: '👥',
                points: 40
            },
            {
                id: 'seasonal_expert',
                title: 'Esperto Stagionale',
                description: 'Partecipa a eventi in tutte e 4 le stagioni',
                icon: '🍂',
                points: 60
            },
            {
                id: 'loyalty_member',
                title: 'Membro Fedele',
                description: 'Completa 20 prenotazioni',
                icon: '💎',
                points: 100
            }
        ];
    }

    getAllBadges() {
        return [
            {
                id: 'wine_expert',
                title: 'Esperto di Vini',
                description: 'Partecipa a 5 degustazioni di vino',
                icon: '🍷'
            },
            {
                id: 'food_lover',
                title: 'Amante del Cibo',
                description: 'Prenota 10 esperienze gastronomiche',
                icon: '🍽️'
            },
            {
                id: 'adventure_seeker',
                title: 'Cercatore di Avventure',
                description: 'Completa 5 attività all\'aperto',
                icon: '🏔️'
            },
            {
                id: 'culture_enthusiast',
                title: 'Appassionato di Cultura',
                description: 'Visita 8 musei o siti culturali',
                icon: '🏛️'
            },
            {
                id: 'night_owl',
                title: 'Gufo Notturno',
                description: 'Prenota 3 eventi serali',
                icon: '🦉'
            },
            {
                id: 'weekend_warrior',
                title: 'Guerriero del Weekend',
                description: 'Prenota 10 eventi nel weekend',
                icon: '⚔️'
            }
        ];
    }

    async loadLeaderboard(period = 'weekly') {
        const content = document.getElementById('wcefp-leaderboard-content');
        if (!content) return;

        content.innerHTML = '<div class="wcefp-loading">Caricamento...</div>';

        try {
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'wcefp_get_leaderboard',
                    nonce: this.nonce,
                    period: period
                })
            });

            const data = await response.json();
            if (data.success) {
                this.renderLeaderboard(data.data, content);
            }
        } catch (error) {
            content.innerHTML = '<div class="wcefp-error">Errore nel caricamento della classifica</div>';
        }
    }

    renderLeaderboard(leaderboardData, container) {
        const html = leaderboardData.map((user, index) => `
            <div class="wcefp-leaderboard-item ${user.is_current_user ? 'current-user' : ''}">
                <div class="wcefp-leaderboard-rank">${this.getRankIcon(index + 1)}</div>
                <div class="wcefp-leaderboard-avatar">
                    <img src="${user.avatar || '/wp-content/uploads/default-avatar.png'}" alt="${user.name}">
                </div>
                <div class="wcefp-leaderboard-info">
                    <div class="wcefp-leaderboard-name">${user.name}</div>
                    <div class="wcefp-leaderboard-level">Livello ${user.level}</div>
                </div>
                <div class="wcefp-leaderboard-points">${user.points} punti</div>
            </div>
        `).join('');

        container.innerHTML = `<div class="wcefp-leaderboard-list">${html}</div>`;
    }

    getRankIcon(position) {
        const icons = {
            1: '🥇',
            2: '🥈',
            3: '🥉'
        };
        return icons[position] || `#${position}`;
    }

    // Utility methods
    formatDate(timestamp) {
        return new Date(timestamp).toLocaleDateString('it-IT');
    }

    easeOutCubic(x) {
        return 1 - Math.pow(1 - x, 3);
    }

    getTotalBadgesCount() {
        return this.getAllBadges().length;
    }

    toggleAchievementsPanel() {
        const content = document.getElementById('wcefp-achievements-content');
        if (content) {
            content.classList.toggle('wcefp-show');
        }
    }

    updateUI() {
        this.updateLevelDisplay();
        this.updateProgressBar();
        this.updateAchievementsDisplay();
        this.updateBadgesDisplay();
    }

    updateLevelDisplay() {
        const levelElement = document.getElementById('wcefp-user-level');
        if (levelElement) {
            levelElement.textContent = `Lv. ${this.userLevel}`;
        }
    }

    updateProgressBar() {
        const progressFill = document.getElementById('wcefp-progress-fill');
        const progressText = document.getElementById('wcefp-progress-text');
        
        if (progressFill) {
            const progress = this.getProgressToNextLevel();
            progressFill.style.width = `${progress}%`;
        }
        
        if (progressText) {
            progressText.textContent = `${Math.round(this.getProgressToNextLevel())}%`;
        }
    }

    updateAchievementsDisplay() {
        const grid = document.getElementById('wcefp-achievements-grid');
        if (grid) {
            grid.innerHTML = this.renderAchievements();
        }
    }

    updateBadgesDisplay() {
        const grid = document.getElementById('wcefp-badges-grid');
        if (grid) {
            grid.innerHTML = this.renderBadges();
        }
    }

    initializeNotifications() {
        // Load initial leaderboard
        this.loadLeaderboard();
        
        // Check for daily login bonus
        this.checkDailyLoginBonus();
    }

    async checkDailyLoginBonus() {
        const lastLogin = localStorage.getItem('wcefp-last-login');
        const today = new Date().toDateString();
        
        if (lastLogin !== today) {
            localStorage.setItem('wcefp-last-login', today);
            this.awardPoints('daily_login', { date: today });
        }
    }

    trackConsecutiveBookings() {
        // This would typically be handled server-side
        // but can be enhanced with client-side tracking
    }

    trackSeasonalActivities() {
        const currentSeason = this.getCurrentSeason();
        const lastSeasonCheck = localStorage.getItem('wcefp-last-season-check');
        
        if (lastSeasonCheck !== currentSeason) {
            localStorage.setItem('wcefp-last-season-check', currentSeason);
            // Award seasonal activity points if user books something
            document.addEventListener('wcefp-booking-completed', () => {
                this.awardPoints('seasonal_event', { season: currentSeason });
            }, { once: true });
        }
    }

    getCurrentSeason() {
        const month = new Date().getMonth();
        if (month >= 2 && month <= 4) return 'spring';
        if (month >= 5 && month <= 7) return 'summer';
        if (month >= 8 && month <= 10) return 'autumn';
        return 'winter';
    }
}

// Initialize gamification system
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.wcefpGamification = new WCEFPGamification();
    });
} else {
    window.wcefpGamification = new WCEFPGamification();
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = WCEFPGamification;
}