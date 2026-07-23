/**
 * @file
 * Dashboard filter AJAX behaviors for Ticket Desk.
 */

(function (Drupal, once) {
  /**
   * Updates the browser URL to reflect active dashboard filters.
   *
   * @param {Object} filters
   *   Filter key/value pairs.
   */
  Drupal.ticketdeskDashboardUpdateUrl = (filters) => {
    const params = new URLSearchParams(window.location.search);
    const keys = ['requester', 'assignee', 'status', 'priority'];

    keys.forEach((key) => {
      params.delete(key);
      if (filters[key]) {
        params.set(key, filters[key]);
      }
    });

    const query = params.toString();
    const nextUrl = query
      ? `${window.location.pathname}?${query}`
      : window.location.pathname;
    window.history.replaceState({}, '', nextUrl);
  };

  Drupal.AjaxCommands.prototype.ticketdeskDashboardResetFilters = function ticketdeskDashboardResetFilters() {
    document.querySelectorAll('.ticketdesk-dashboard__filters-form select').forEach((select) => {
      select.selectedIndex = 0;
    });
  };

  Drupal.AjaxCommands.prototype.ticketdeskDashboardUpdateUrl = function ticketdeskDashboardUpdateUrl(
    ajax,
    response,
  ) {
    Drupal.ticketdeskDashboardUpdateUrl(response.filters || {});
    const results = document.getElementById('ticketdesk-dashboard-results');
    if (results) {
      results.classList.remove('is-loading');
    }
  };

  Drupal.behaviors.ticketdeskDashboard = {
    attach(context) {
      once('ticketdesk-dashboard-form', '.ticketdesk-dashboard__filters-form', context).forEach((form) => {
        form.querySelectorAll('input[type="submit"]').forEach((button) => {
          button.addEventListener('mousedown', () => {
            document.getElementById('ticketdesk-dashboard-results')?.classList.add('is-loading');
          });
        });
      });
    },
  };
})(Drupal, once);
