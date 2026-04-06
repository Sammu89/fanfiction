(function($) {
    'use strict';

    var strings = window.fanficLoadMore && window.fanficLoadMore.strings ? window.fanficLoadMore.strings : {};
    var loadMoreLabel = strings.loadMore || 'Load more...';
    var loadingLabel = strings.loading || 'Loading...';
    var spinnerHtml = '<span class="fanfic-load-more-button__spinner" aria-hidden="true"></span>';

    function getRegionKey($region) {
        return String($region.attr('data-fanfic-load-more-key') || '');
    }

    function getPagination($region) {
        return $region.find('[data-fanfic-load-more-pagination]').first();
    }

    function getList($region) {
        return $region.find('[data-fanfic-load-more-list]').first();
    }

    function getNextUrl($region) {
        var $pagination = getPagination($region);
        if (!$pagination.length) {
            return '';
        }

        var $nextLink = $pagination.find('a.next, a.next.page-numbers, .page-numbers.next').first();
        return String($nextLink.attr('href') || '');
    }

    function getControls($region) {
        var $controls = $region.children('[data-fanfic-load-more-controls]');
        if ($controls.length) {
            return $controls.first();
        }

        $controls = $('<div class="fanfic-load-more-controls" data-fanfic-load-more-controls></div>');
        var $pagination = getPagination($region);
        if ($pagination.length) {
            $controls.insertAfter($pagination);
        } else {
            $region.append($controls);
        }

        return $controls;
    }

    function syncButton($region) {
        var nextUrl = getNextUrl($region);
        var $pagination = getPagination($region);
        var $controls = getControls($region);
        var $button = $controls.find('.fanfic-load-more-button');

        if ($pagination.length) {
            $pagination.hide();
        }

        if (!nextUrl) {
            $controls.remove();
            return;
        }

        if (!$button.length) {
            $button = $('<button type="button" class="fanfic-button fanfic-load-more-button"></button>');
            $controls.empty().append($button);
        }

        $button
            .attr('data-next-url', nextUrl)
            .prop('disabled', false)
            .removeClass('is-loading')
            .html(spinnerHtml + '<span class="fanfic-load-more-button__label">' + loadMoreLabel + '</span>');
    }

    function parseRegionFromHtml(html, key) {
        var nodes = $.parseHTML(html, document, true) || [];
        var $document = $('<div></div>').append(nodes);

        return $document.find('[data-fanfic-load-more-region]').filter(function() {
            return $(this).attr('data-fanfic-load-more-key') === key;
        }).first();
    }

    function replacePagination($region, $sourceRegion) {
        var $currentPagination = getPagination($region);
        var $sourcePagination = getPagination($sourceRegion);

        if ($sourcePagination.length) {
            if ($currentPagination.length) {
                $currentPagination.replaceWith($sourcePagination);
            } else {
                $region.append($sourcePagination);
            }
        } else if ($currentPagination.length) {
            $currentPagination.remove();
        }
    }

    function notifyAppend($region, appendedCount) {
        document.dispatchEvent(new CustomEvent('fanfic:load-more-appended', {
            detail: {
                region: $region.get(0),
                appendedCount: appendedCount
            }
        }));
    }

    function loadNextPage($button) {
        var $region = $button.closest('[data-fanfic-load-more-region]');
        var nextUrl = String($button.attr('data-next-url') || '');

        if (!nextUrl || !$region.length || $region.data('fanficLoadMoreBusy')) {
            return;
        }

        $region.data('fanficLoadMoreBusy', true);
        $button
            .prop('disabled', true)
            .addClass('is-loading')
            .html(spinnerHtml + '<span class="fanfic-load-more-button__label">' + loadingLabel + '</span>');

        $.get(nextUrl)
            .done(function(html) {
                var key = getRegionKey($region);
                var $nextRegion = parseRegionFromHtml(html, key);

                if (!$nextRegion.length) {
                    window.location.href = nextUrl;
                    return;
                }

                var $currentList = getList($region);
                var $nextList = getList($nextRegion);

                if (!$currentList.length || !$nextList.length) {
                    window.location.href = nextUrl;
                    return;
                }

                var $newItems = $nextList.children();
                if ($newItems.length) {
                    $newItems.addClass('fanfic-load-more-item');
                    $currentList.append($newItems);
                    window.requestAnimationFrame(function() {
                        $newItems.addClass('is-visible');
                    });
                }

                replacePagination($region, $nextRegion);
                syncButton($region);
                notifyAppend($region, $newItems.length);
            })
            .fail(function() {
                window.location.href = nextUrl;
            })
            .always(function() {
                $region.data('fanficLoadMoreBusy', false);
                syncButton($region);
            });
    }

    function initRegion($region) {
        if (!$region.length || $region.data('fanficLoadMoreInitialized')) {
            return;
        }

        $region.data('fanficLoadMoreInitialized', true);
        syncButton($region);
    }

    $(document).ready(function() {
        $('[data-fanfic-load-more-region]').each(function() {
            initRegion($(this));
        });
    });

    $(document).on('click', '.fanfic-load-more-button', function(e) {
        e.preventDefault();
        loadNextPage($(this));
    });
})(jQuery);
