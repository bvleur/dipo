(function($) {
  "use strict";

  $.fn.portfolioBrowser = function(initialId) {
    var dotNav = this.find('.dot-nav li');
    var container = this.find('.elementcontainer');
    var description = this.find('figcaption');
    var tagcontainer = this.find('.elementtags');
    var taglist = tagcontainer.find('ul');
    var current = container.find('.element');
    var currentIdx;

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
        'margin-top': -1 * (child.height() / 2)
      });
    }

    container.css({'position': 'relative'});
    absoluteCenter(current);

    /* List of elements */
    var default_description;
    var elements;

    function findElementIndex(id) {
      for (var idx = 0; idx < elements.length; idx++)
          if (elements[idx].id == id)
            return idx;

      // TODO not found: return a fake error element?
      alert('not-found');
    }

    /* Get the staged (pre-loaded) element contents. If it isn't, stage it now
     * by adding the HTML to the container */
    function getStagedElement(element) {
      if (typeof element.staged === "undefined") {
        var stagedHtml = $(element.html);
        stagedHtml.css({'display': 'none'});
        container.append(stagedHtml);
        absoluteCenter(stagedHtml);
        element.staged = stagedHtml;
      }

      return element.staged;
    }


    /* Transition the current element to element with id "id" and update meta-data and navigation */
    function showElement(id, noPushState) {
      /* Note: id isn't necessarily an element code. Element codes are only
       * guaranteed to be unique in a group. It's a string uniquely identifying an element in a browsing set.
       */
      var idx = findElementIndex(id);
      if (idx == currentIdx)
        return; /* Nothing to do, this element is already shown. Don't animate */

      var element = elements[idx];

      var staged = getStagedElement(element);

      /* Cross-fade the new element in (and hide the old afterwards) */
      staged.css({'display': 'block', 'opacity': 0});
      staged.animate({'opacity': 1});
      current.animate({'opacity': 0}, function () {
        current.css({'display': 'none'});

        /* The staged element should now be considered current as the old one is hidden */
        current = staged;
        currentIdx = idx;

        preloadNextPrev();
      });

      /* Update description */
      description.html(element.description || default_description);

      /* Update tags */
      updateTags(element.tags)

      /* Update navigation */
      dotNav.eq(currentIdx).removeClass('current');
      dotNav.eq(idx).addClass('current');

      /* Change the URL (ignore older browsers) */
      if (Modernizr.history && noPushState)
        window.history.pushState({id: id}, null, encodeURIComponent(id));
    }

    function updateTags(elementTags) {
      /* Determine mutations (movements, removals, additions) */
      var elementTagNames = $.map(elementTags, function (a) { return a.name + ""; });
      var notNew = [];
      var toRemove = [];
      var toMove = [];

      taglist.find('li').each(function(currentIndex) {
        var newIndex = $.inArray($(this).text(), elementTagNames);
        if (newIndex === -1) {
          toRemove.push(this)
        } else {
          notNew.push(newIndex);
          if (newIndex != currentIndex) {
            toMove.push({tag: this, from: currentIndex, to: newIndex});
          }
        }
      });

      /* Effectuate mutations */
      $.each(toRemove, function (index, tag) { $(tag).remove(); });
      $.each(elementTags, function (index, elementTag) {
        if ($.inArray(index, notNew) === -1) {
          var newTag = '<li><a href="/portfolio/' + elementTag.code + '/' + elementTag.firstElementId + '">' + elementTag.name + '</a></li>';
          if (index == 0) {
            taglist.prepend(newTag);
          } else {
            taglist.find('li').eq(index - 1).after(newTag);
          }
        }
      });

      $.each(toMove, function (index, movement) {
        if (taglist.find('li').eq(movement.to).is(movement.tag)) {
          return;
        }
        if (movement.to == 0) {
          taglist.find('li').eq(0).before($(movement.tag));
        } else {
          if (movement.from < movement.to) {
            taglist.find('li').eq(movement.to).after($(movement.tag));
          } else {
            taglist.find('li').eq(movement.to - 1).after($(movement.tag));
          }
        }
      });

      tagcontainer.toggle((elementTags.length !== 0));
    }

    /* Preload the previous and next elements by already requesting the element on stage */
    function preloadNextPrev() {
      if ((currentIdx + 1) < elements.length) {
        getStagedElement(elements[currentIdx + 1]);
      }
      if ((currentIdx - 1) >= 0) {
        getStagedElement(elements[currentIdx - 1]);
      }
    }

    /* Make existing navigation elements in the page use this portfolio browser for switching */
    function attachToNavigation() {
      dotNav.find('a').click(function (e) {
        showElement(decodeURIComponent($(this).attr('href')), true);
        return false;
      });

      if (Modernizr.history) {
        window.onpopstate = function (event) {
          showElement(event.state.id);
        }
        window.history.replaceState({id: initialId}, null);
      }
    }

    /* Retrive the data for the current browsing set */
    $.getJSON('browser-data', function(data) {
      default_description = data['description']
      elements = data['elements'];
      attachToNavigation();

      currentIdx = findElementIndex(initialId);
      preloadNextPrev();
    });
  };

})(jQuery);
