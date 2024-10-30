jQuery(document).ready(function($) {
    var selectedColor = '#FFFF00'; // Default color
    var currentHighlight = null; // Currently selected highlight element

    // Toggle color picker visibility
    $('#highlighter-dot').on('click', function() {
        $('#highlighter-color-picker').toggle();
    });

    // Handle color selection
    $('.color-option').on('click', function() {
        selectedColor = $(this).data('color');
        $('#highlighter-dot').css('background-color', selectedColor);
        $('#highlighter-color-picker').hide();

        if (currentHighlight) {
            // Change color of existing highlight
            $(currentHighlight).css('background-color', selectedColor);

            // Update highlight data on the server
            var start = $(currentHighlight).data('startOffset');
            var end = $(currentHighlight).data('endOffset');

            $.ajax({
                url: highlighter_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'update_highlight',
                    nonce: highlighter_ajax.nonce,
                    post_id: highlighter_ajax.post_id,
                    start_offset: start,
                    end_offset: end,
                    color: selectedColor,
                },
                success: function(response) {
                    if (response.success) {
                        currentHighlight = null;
                    } else {
                        console.error('Error updating highlight:', response.data);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX error:', textStatus, errorThrown);
                }
            });
        }
    });

    // Handle remove highlight
    $('#highlighter-remove-icon').on('click', function() {
        if (currentHighlight) {
            // Remove highlight from DOM
            var $highlight = $(currentHighlight);
            var start = $highlight.data('startOffset');
            var end = $highlight.data('endOffset');
            $highlight.contents().unwrap();

            // Remove highlight from server
            $.ajax({
                url: highlighter_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'delete_highlight',
                    nonce: highlighter_ajax.nonce,
                    post_id: highlighter_ajax.post_id,
                    start_offset: start,
                    end_offset: end,
                },
                success: function(response) {
                    if (response.success) {
                        currentHighlight = null;
                        $('#highlighter-color-picker').hide();
                    } else {
                        console.error('Error deleting highlight:', response.data);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX error:', textStatus, errorThrown);
                }
            });
        }
    });

    // Function to apply highlights from the server
    function applyHighlights(highlights) {
        highlights.forEach(function(highlight) {
            var contentElement = document.getElementById('highlighter-content');
            var startNode = getTextNodeByOffset(contentElement, highlight.start_offset);
            var endNode = getTextNodeByOffset(contentElement, highlight.end_offset);

            if (startNode && endNode) {
                var range = document.createRange();
                range.setStart(startNode.node, startNode.offset);
                range.setEnd(endNode.node, endNode.offset);

                if (!isValidRange(range)) {
                    console.error('Invalid range for highlight:', highlight);
                    return;
                }

                var span = document.createElement('span');
                span.style.backgroundColor = highlight.color;
                span.className = 'highlighter-highlight';
                span.dataset.startOffset = highlight.start_offset;
                span.dataset.endOffset = highlight.end_offset;

                try {
                    // Use extractContents and insertNode to apply the highlight
                    var contents = range.extractContents();
                    span.appendChild(contents);
                    range.insertNode(span);
                } catch (e) {
                    console.error('Error applying highlight:', e);
                }
            } else {
                console.error('Could not find nodes for highlight:', highlight);
            }
        });
    }

    // Function to get text node by offset
    function getTextNodeByOffset(root, offset) {
        var treeWalker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null, false);
        var currentNode;
        var currentOffset = 0;
        while ((currentNode = treeWalker.nextNode())) {
            var nextOffset = currentOffset + currentNode.length;
            if (currentOffset <= offset && offset < nextOffset) {
                return { node: currentNode, offset: offset - currentOffset };
            }
            currentOffset = nextOffset;
        }
        return null;
    }

    // Function to check if a range is valid
    function isValidRange(range) {
        if (range.collapsed) {
            return false;
        }

        // Function to check if a node or its descendants contain text
        function hasTextContent(node) {
            if (node.nodeType === Node.TEXT_NODE && node.textContent.trim() !== '') {
                return true;
            }
            for (var i = 0; i < node.childNodes.length; i++) {
                if (hasTextContent(node.childNodes[i])) {
                    return true;
                }
            }
            return false;
        }

        if (!hasTextContent(range.cloneContents())) {
            return false;
        }

        return true;
    }

    // Function to adjust range to text nodes
    function adjustRangeToTextNodes(range) {
        var newRange = range.cloneRange();

        // Adjust start
        if (newRange.startContainer.nodeType !== Node.TEXT_NODE) {
            var startNode = newRange.startContainer.childNodes[newRange.startOffset];
            while (startNode && startNode.nodeType !== Node.TEXT_NODE) {
                if (startNode.childNodes.length > 0) {
                    startNode = startNode.childNodes[0];
                } else {
                    break;
                }
            }
            if (startNode && startNode.nodeType === Node.TEXT_NODE) {
                newRange.setStart(startNode, 0);
            }
        }

        // Adjust end
        if (newRange.endContainer.nodeType !== Node.TEXT_NODE) {
            var endNode = newRange.endContainer.childNodes[newRange.endOffset - 1];
            while (endNode && endNode.nodeType !== Node.TEXT_NODE) {
                if (endNode.childNodes.length > 0) {
                    endNode = endNode.childNodes[endNode.childNodes.length - 1];
                } else {
                    break;
                }
            }
            if (endNode && endNode.nodeType === Node.TEXT_NODE) {
                newRange.setEnd(endNode, endNode.textContent.length);
            }
        }

        return newRange;
    }

    // Load highlights on page load
    $.ajax({
        url: highlighter_ajax.ajax_url,
        method: 'POST',
        data: {
            action: 'get_highlights',
            nonce: highlighter_ajax.nonce,
            post_id: highlighter_ajax.post_id,
        },
        success: function(response) {
            if (response.success) {
                applyHighlights(response.data);
            } else {
                console.error('Error retrieving highlights:', response.data);
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.error('AJAX error:', textStatus, errorThrown);
        }
    });

    // Handle text selection and highlight saving
    $(document).on('mouseup', function(e) {
        var selection = window.getSelection();

        if (!selection.isCollapsed && selection.rangeCount > 0) {
            var range = selection.getRangeAt(0);

            // Ensure selection is within the content area
            var contentElement = document.getElementById('highlighter-content');
            if (!contentElement.contains(range.commonAncestorContainer)) {
                return;
            }

            // Adjust the range to start and end at text nodes
            range = adjustRangeToTextNodes(range);

            // Validate the adjusted range
            if (!isValidRange(range)) {
                console.error('Selected range is invalid.');
                selection.removeAllRanges();
                return;
            }

            // Check if selection is within an existing highlight
            var parentHighlight = $(selection.anchorNode).closest('.highlighter-highlight');
            if (parentHighlight.length > 0) {
                // Set current highlight for color change or removal
                currentHighlight = parentHighlight[0];
                // Show color picker
                $('#highlighter-color-picker').show();
                selection.removeAllRanges();
                return;
            }

            // Get start and end offsets relative to content element
            var preSelectionRange = range.cloneRange();
            preSelectionRange.selectNodeContents(contentElement);
            preSelectionRange.setEnd(range.startContainer, range.startOffset);
            var start = preSelectionRange.toString().length;

            var selectedText = range.toString();
            var end = start + selectedText.length;

            // Send highlight data to server
            $.ajax({
                url: highlighter_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'save_highlight',
                    nonce: highlighter_ajax.nonce,
                    post_id: highlighter_ajax.post_id,
                    start_offset: start,
                    end_offset: end,
                    color: selectedColor,
                },
                success: function(response) {
                    if (response.success) {
                        // Apply highlight locally
                        var span = document.createElement('span');
                        span.style.backgroundColor = selectedColor;
                        span.className = 'highlighter-highlight';
                        span.dataset.startOffset = start;
                        span.dataset.endOffset = end;

                        try {
                            // Use extractContents and insertNode to apply the highlight
                            var contents = range.extractContents();
                            span.appendChild(contents);
                            range.insertNode(span);
                        } catch (e) {
                            console.error('Error applying highlight locally:', e);
                        }
                        selection.removeAllRanges();
                    } else {
                        console.error('Error saving highlight:', response.data);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX error:', textStatus, errorThrown);
                }
            });
        }
    });

    // Handle click on existing highlights
    $(document).on('click', '.highlighter-highlight', function(e) {
        e.stopPropagation(); // Prevent event from bubbling up
        currentHighlight = this;
        // Show color picker
        $('#highlighter-color-picker').show();
    });

    // Hide color picker when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#highlighter-color-picker').length &&
            !$(e.target).is('#highlighter-dot') &&
            !$(e.target).is('#highlighter-remove-icon')) {
            $('#highlighter-color-picker').hide();
            currentHighlight = null;
        }
    });
});
