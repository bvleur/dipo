(function($) {
  "use strict";

  $.fn.portfolioBrowser = function() {
    var dot_nav = this.find('.dot-nav');
    var container = this.find('.elementcontainer');
    var current = container.find('.element');

    /* Multiple elements in the container need to overlap during the transition.
     * Therefore elements need to be absolutely positioned in a relative positioned container.
     * Absolute positioning however destroys the native CSS centering using display:table-cell and vertical-align:middle
     * therefore we transform the elements into positioning with negative margins
     */
    function absoluteCenter(el) {
      var child = el.children().first();
      el.css({
        'position': 'absolute',
        'top': '50%',
        'left': '50%',
        'margin-left': -1 * (child.width() / 2),
        'margin-top': -1 * (child.height() / 2),
      });
    }

    container.css({'position': 'relative'});
    absoluteCenter(current);

    /* List of elements */
    var elements;

    function getElementIndex(id) {
      for (var idx in elements)
          if (elements[idx].id == id)
            return idx;

      // TODO not found: return a fake error element?
      alert('not-found');
    }

    function getElement(id, ensureStaged) {
      var element = elements[getElementIndex(id)];

      if ((typeof ensureStaged !== "undefinded" && ensureStaged)
          && (typeof element.staged === "undefined"))
        stageElement(element);

      return element;
    }

    /* Pre-load an elements contents by adding the HTML to the container */
    function stageElement(element) {
      var stagedHtml = $(element.html);
      absoluteCenter(stagedHtml);
      stagedHtml.css({'display': 'none'});
      container.append(stagedHtml);
      element.staged = stagedHtml;
    }


    /* Transition the current element to element with id "id" and update meta-data and navigation */
    function showElement(id) {
      /* Note: id isn't necessarily an element code. Element codes are only
       * guaranteed to be unique in a group. It's a string uniquely identifying an element in a browsing set.
       */
      var element = getElement(id, true);

      var show = element.staged;
      var hide = current;

      show.css({'display': 'block', 'opacity': 0});
      show.animate({'opacity': 1});
      hide.animate({'opacity': 0}, function () {
        hide.css({'display': 'none'});
      });

      // TODO update meta-data
      // TODO update navigation
      current = show;
    }

    /* Make existing navigation elements in the page use this portfolio browser for switching */
    function attachToNavigation() {
      dot_nav.find('a').click(function () {
        showElement($(this).attr('href'));
        return false;
      });
    }

    /* Retrive the data for the current browsing set */
    $.getJSON('browser-data', function(data) {
      elements = data;
      attachToNavigation();
    });
  };
})( jQuery );
