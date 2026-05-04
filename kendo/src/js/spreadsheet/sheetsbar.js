/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "../kendo.core.js";
import "../kendo.dom.js";
import "../kendo.sortable.js";
import "../kendo.icons.js";

    (function(kendo) {

        var $ = kendo.jQuery;
        var outerWidth = kendo._outerWidth;
        var DOT = ".";
        var EMPTYCHAR = " ";
        var sheetsBarClassNames = {
            sheetsBarWrapper: "k-spreadsheet-sheets-bar",
            sheetsBarSheetsWrapper: "k-tabstrip k-tabstrip-bottom",
            sheetsBarAdd: "k-spreadsheet-sheet-add",
            sheetsBarSheetsMenu: "k-spreadsheet-sheets-menu",
            sheetsBarRemove: "k-spreadsheet-sheets-remove",
            sheetsBarItems: "k-spreadsheet-sheets",
            sheetsBarEditor: "k-spreadsheet-sheets-editor",
            sheetsBarScrollable: "k-tabstrip-scrollable",
            sheetsBarNext: "k-tabstrip-next",
            sheetsBarPrev: "k-tabstrip-prev",
            sheetsBarKItem: "k-item",
            sheetsBarKTabstripItem: "k-tabstrip-item",
            sheetsBarKActive: "k-active",
            sheetsBarKTextbox: "k-textbox",
            sheetsBarKLink: "k-link",
            sheetsBarKLinkText: "k-link-text",
            sheetsBarKIcon: "k-icon",
            sheetsBarKFontIcon: "k-icon",
            sheetsBarKButton: "k-button k-icon-button",
            sheetsBarKButtonDefaults: "",
            sheetsBarKButtonBare: "k-button-flat",
            sheetsBarArrowWIcon: "caret-alt-left",
            sheetsBarArrowEIcon: "caret-alt-right",
            sheetsBarKReset: "k-reset k-tabstrip-items k-tabstrip-items-start",
            sheetsBarXIcon: "x",
            sheetsBarMoreIcon: "caret-alt-down",
            sheetsBarKSprite: "k-sprite",
            sheetsBarPlusIcon: "plus",
            sheetsBarMenuIcon: "menu",
            sheetsBarHintWrapper: "k-widget k-tabstrip k-tabstrip-bottom k-spreadsheet-sheets-items-hint",
            sheetsBarKResetItems: "k-reset k-tabstrip-items k-tabstrip-items-start"
        };

        var SheetsBar = kendo.ui.Widget.extend({
            init: function(element, options) {
                var classNames = SheetsBar.classNames;

                kendo.ui.Widget.call(this, element, options);

                element = this.element;

                element.addClass(classNames.sheetsBarWrapper);

                this._openDialog = options.openDialog;

                this._addButton();
                this._menuButton();
                this._tree = new kendo.dom.Tree(element[0]);

                this._tree.render([this._createSheetsWrapper([])]);
                this._initSheetDropDownButtons();

                this._toggleScrollEvents(true);

                this._createSortable();

                this._sortable.bind("start", this._onSheetReorderStart.bind(this));

                this._sortable.bind("end", this._onSheetReorderEnd.bind(this));

                element.on("click", "[ref-sheetsbar-more-button]", this._onSheetContextMenu.bind(this));

                element.on("click", "li", this._onSheetSelect.bind(this));

                element.on("dblclick", "li" + DOT + classNames.sheetsBarKItem + DOT + classNames.sheetsBarKItem, this._createEditor.bind(this));
            },

            options: {
                name: "SheetsBar",
                scrollable: {
                    distance: 200
                }
            },

            events: [
                "select",
                "reorder",
                "rename"
            ],

            _createEditor: function() {
                if (this._editor) {
                    return;
                }

                this._renderSheets(this._sheets, this._selectedIndex, true);
                this._editor = this.element
                    .find(kendo.format("input{0}{1}", DOT, SheetsBar.classNames.sheetsBarEditor))
                    .trigger("focus")
                    .on("keydown", this._onEditorKeydown.bind(this))
                    .on("blur", this._onEditorBlur.bind(this));
            },

            _destroyEditor: function(canceled) {
                var newSheetName = canceled ? null : this._editor.val();
                this._editor.off();
                this._editor = null;
                this._renderSheets(this._sheets, this._selectedIndex, false);
                this._onSheetRename(newSheetName);
            },

            renderSheets: function(sheets, selectedIndex) {
                if (!sheets || selectedIndex < 0) {
                    return;
                }

                this._renderSheets(sheets, selectedIndex, false);
            },

            _renderSheets: function(sheets, selectedIndex, isInEditMode) {
                var that = this;
                var classNames = SheetsBar.classNames;

                that._isRtl = kendo.support.isRtl(that.element);
                that._sheets = sheets;
                that._selectedIndex = selectedIndex;

                that._renderHtml(isInEditMode, true);

                if (!that._scrollableAllowed()) {
                    return;
                }

                var sheetsWrapper = that._sheetsWrapper();

                sheetsWrapper.addClass(classNames.sheetsBarScrollable + EMPTYCHAR + classNames.sheetsBarSheetsWrapper);

                that._toggleScrollButtons();
            },

            _toggleScrollButtons: function(toggle) {
                var that = this;
                var ul = that._sheetsGroup();
                var wrapper = that._sheetsWrapper();
                var scrollLeft = kendo.scrollLeft(ul);
                var prev = wrapper.find(DOT + SheetsBar.classNames.sheetsBarPrev);
                var next = wrapper.find(DOT + SheetsBar.classNames.sheetsBarNext);

                if (toggle === false) {
                    prev.addClass('k-disabled');
                    next.addClass('k-disabled');
                } else {
                    prev.toggleClass('k-disabled', !(that._isRtl ? scrollLeft < ul[0].scrollWidth - ul[0].offsetWidth - 1 : scrollLeft !== 0));
                    next.toggleClass('k-disabled', !(that._isRtl ? scrollLeft !== 0 : scrollLeft < ul[0].scrollWidth - ul[0].offsetWidth - 1));
                }

            },

            _toggleScrollEvents: function(toggle) {
                var that = this;
                var classNames = SheetsBar.classNames;
                var options = that.options;
                var scrollPrevButton;
                var scrollNextButton;
                var sheetsWrapper = that._sheetsWrapper();
                scrollPrevButton = sheetsWrapper.find(DOT + classNames.sheetsBarPrev);
                scrollNextButton = sheetsWrapper.find(DOT + classNames.sheetsBarNext);

                if (toggle) {
                    scrollPrevButton.on("mousedown", function(event) {
                        event.preventDefault();
                        event.stopPropagation();
                        that._nowScrollingSheets = true;
                        that._scrollSheetsByDelta(options.scrollable.distance * (that._isRtl ? 1 : -1));
                    });

                    scrollNextButton.on("mousedown", function(event) {
                        event.preventDefault();
                        event.stopPropagation();
                        that._nowScrollingSheets = true;
                        that._scrollSheetsByDelta(options.scrollable.distance * (that._isRtl ? -1 : 1));
                    });

                    scrollPrevButton.add(scrollNextButton).on("mouseup", function() {
                        that._nowScrollingSheets = false;
                    });
                } else {
                    scrollPrevButton.off();
                    scrollNextButton.off();
                }
            },

            _renderHtml: function(isInEditMode, renderScrollButtons) {
                var idx;
                var sheetElements = [];
                var dom = kendo.dom;
                var element = dom.element;
                var sheets = this._sheets;
                var selectedIndex = this._selectedIndex;
                var classNames = SheetsBar.classNames;

                for (idx = 0; idx < sheets.length; idx++) {
                    var sheet = sheets[idx];

                    var isSelectedSheet = (idx === selectedIndex);
                    var attr = { className: classNames.sheetsBarKItem + EMPTYCHAR + classNames.sheetsBarKTabstripItem + EMPTYCHAR, role: "tab" };
                    var elementContent = [];
                    if (sheet.state() !== 'visible') {
                        attr.className += "k-hidden ";
                    }

                    if (isSelectedSheet) {
                        attr.className += classNames.sheetsBarKActive;
                    }

                    if (isSelectedSheet && isInEditMode) {
                        elementContent.push(element("input", {
                            type: "text",
                            value: sheet.name(),
                            className: classNames.sheetsBarKTextbox + EMPTYCHAR + classNames.sheetsBarEditor,
                            maxlength: 50
                        }, []));
                    } else {
                        elementContent.push(element("span", {
                            className: classNames.sheetsBarKLink,
                            title: sheet.name()
                        }, [element("span", { className: classNames.sheetsBarKLinkText }, [dom.text(sheet.name())])]));

                        let contextMenuButton = element($(kendo.html.renderButton($(`<button ref-sheetsbar-more-button data-sheet-name="${sheet.name()}" class="k-menu-button"></button>`), {
                            icon: classNames.sheetsBarMoreIcon,
                            fillMode: "flat"
                        }))[0]);

                        elementContent.push(element("span", {
                            className: "k-item-actions",
                            'data-type': 'context-menu',
                        }, [contextMenuButton]));
                    }

                    sheetElements.push(element("li", attr, elementContent));
                }

                kendo.destroy(this._sheetsWrapper());
                this._addButton();
                this._menuButton();
                this._tree.render([ this._createSheetsWrapper(sheetElements, renderScrollButtons)]);
                this._initSheetDropDownButtons();
            },
            _initSheetDropDownButtons: function() {
                let that = this;
                this.element.find("[ref-sheetsbar-more-button]").each(function(ind, btnEl){
                    let el = $(btnEl);
                    let allSheets = that._sheets || [];
                    let visibleSheets = allSheets.filter(sheet => sheet.state() === 'visible');
                    let isSingleVisibleSheet = !that._sheets || that._sheets && visibleSheets.length < 2;
                    let sheetName = el.data("sheetName");
                    let shouldAllowMoveRight = !(isSingleVisibleSheet || (that._sheets && ind == allSheets.length - 1));
                    let shouldAllowMoveLeft = !(isSingleVisibleSheet || (that._sheets && ind === 0));
                    el.kendoDropDownButton({
                        icon: SheetsBar.classNames.sheetsBarMoreIcon,
                        fillMode: "flat",
                        items: [
                            { text: "Delete", icon: "trash", attributes: { "data-command": "delete", "data-sheet-name": sheetName }, enabled: !isSingleVisibleSheet },
                            { text: "Duplicate", icon: "copy", attributes: { "data-command": "duplicate", "data-sheet-name": sheetName } },
                            { text: "Rename", icon: "pencil", attributes: { "data-command": "rename", "data-sheet-name": sheetName } },
                            { text: "Hide", icon: "eye-slash", attributes: { "data-command": "hide", "data-sheet-name": sheetName }, enabled: !isSingleVisibleSheet },
                            { text: "Move Right", icon: "arrow-right", attributes: { "data-command": "move-right", "data-sheet-name": sheetName }, enabled: shouldAllowMoveRight },
                            { text: "Move Left", icon: "arrow-left", attributes: { "data-command": "move-left", "data-sheet-name": sheetName }, enabled: shouldAllowMoveLeft },
                        ],
                        click: that._onSheetContextMenu.bind(that)
                    });
                })
            },
            _createSheetsWrapper: function(sheetElements, renderScrollButtons) {
                var element = kendo.dom.element;
                var classNames = SheetsBar.classNames;
                var itemsWrapper = element('div', { className: 'k-tabstrip-items-wrapper k-hstack' });
                var childrenElements = [element("ul", {
                    className: classNames.sheetsBarKReset,
                    role: "tablist"
                }, sheetElements), null, null];

                renderScrollButtons = true;

                if (renderScrollButtons) {
                    var baseButtonClass = classNames.sheetsBarKButton + EMPTYCHAR + classNames.sheetsBarKButtonBare + EMPTYCHAR;

                    childrenElements[1] = (element("span", { ariaHidden: "true", tabIndex: -1, className: baseButtonClass + classNames.sheetsBarPrev }, [
                        element($(kendo.ui.icon({ icon: classNames.sheetsBarArrowWIcon, iconClass: "k-button-icon" }))[0])
                    ]));

                    childrenElements[2] = (element("span", { ariaHidden: "true", tabIndex: -1, className: baseButtonClass + classNames.sheetsBarNext }, [
                        element($(kendo.ui.icon({ icon: classNames.sheetsBarArrowEIcon, iconClass: "k-button-icon" }))[0])
                    ]));
                }

                itemsWrapper.children = childrenElements;

                return element("div", { className: classNames.sheetsBarItems }, [itemsWrapper]);
            },

            _createSortable: function() {
                var classNames = SheetsBar.classNames;
                this._sortable = new kendo.ui.Sortable(this.element, {
                    filter: `ul li.${classNames.sheetsBarKItem}`,
                    container: DOT + classNames.sheetsBarItems,
                    axis: "x",
                    animation: false,
                    ignore: "input",
                    end: function() {
                        if (this.draggable.hint) {
                            this.draggable.hint.remove();
                        }
                    },
                    hint: function(element) {
                        var hint = $(element).clone().attr("ref-sheetsbar-sortable-hint", "");
                        return hint.wrap("<div class='" + classNames.sheetsBarHintWrapper + "'><ul class='" + classNames.sheetsBarKResetItems + "'></ul></div>").closest("div");
                    }
                });
            },

            _onEditorKeydown: function(e) {
                if (this._editor) {
                    if (e.which === 13) {
                        this._destroyEditor();
                    }

                    if (e.which === 27) {
                        this._destroyEditor(true);
                    }
                }
            },

            _onEditorBlur: function() {
                if (this._editor) {
                    this._destroyEditor();
                }
            },

            _onSheetReorderEnd: function(e) {
                e.preventDefault();
                this.trigger("reorder", { oldIndex: e.oldIndex, newIndex: e.newIndex });
            },

            _onSheetReorderStart: function(e) {
                if (this._editor) {
                    e.preventDefault();
                }
            },
            _onSheetContextMenu: function(e) {
                let sheetName = $(e.target).closest("li").data("sheetName");
                let command = $(e.target).closest("li").data("command");

                if (this._editor) {
                    this._destroyEditor();
                }

                if (sheetName && command) {
                    switch(command) {
                        case "delete":
                            this._onSheetRemove(e);
                            break;
                        case "duplicate":
                            this._onSheetDuplicate(sheetName);
                            break;
                        case "rename":
                            this._renamePrompt(sheetName);
                            break;
                        case "hide":
                            this._hideSheet(sheetName);
                            break;
                        case "move-right":
                            this._moveSheet(sheetName, 1);
                            break;
                        case "move-left":
                            this._moveSheet(sheetName, -1);
                            break;
                    }
                }
            },
            _onSheetDuplicate: function(sheetName) {
                this.trigger("duplicate", { name: sheetName });
            },
            _renamePrompt: function(sheetName) {
                let renameSheetIndex = this._sheets.findIndex(sh => sh.name() == sheetName);
                let closeCallback = function(e) {
                    let dlg = e.sender;
                    if (dlg._newSheetName && dlg._newSheetName !== sheetName) {
                        this.trigger("rename", { name: dlg._newSheetName, sheetIndex: renameSheetIndex });
                    }
                }.bind(this);

                this._openDialog("renameSheet", {
                    close: closeCallback,
                    _oldSheetName: sheetName
                });
            },
            _hideSheet: function(sheetName) {
                this.trigger("hide", { name: sheetName });
            },
            _moveSheet: function(sheetName, direction) {
                let sheetIndex = this._sheets.findIndex(sheet => sheet.name() === sheetName);
                let newSheetIndex = sheetIndex + direction;
                if (newSheetIndex < 0 || newSheetIndex >= this._sheets.length) {
                    return;
                }

                this.trigger("reorder", { oldIndex: sheetIndex, newIndex: newSheetIndex });
            },
            _onSheetRemove: function(e) {
                var removedSheetName = $(e.target).closest("li").data("sheetName");

                if (this._editor) {
                    this._destroyEditor();
                }

                var closeCallback = function(e) {
                    var dlg = e.sender;

                    if (dlg.isConfirmed()) {
                        this.trigger("remove", { name: removedSheetName, confirmation: true });
                    }
                }.bind(this);

                this._openDialog("confirmation", {
                    close: closeCallback
                });
            },

            _onSheetSelect: function(e) {
                var selectedSheetText = $(e.target).text();

                if ($(e.target).is(DOT + SheetsBar.classNames.sheetsBarEditor) || !selectedSheetText) {
                    e.preventDefault();
                    return;
                }

                if (this._editor) {
                    this._destroyEditor();
                }

                this._scrollSheetsToItem($(e.target).closest("li"));
                this.trigger("select", { name: selectedSheetText, isAddButton: false });
            },

            _onSheetRename: function(newSheetName) {
                if (this._sheets[this._selectedIndex].name() === newSheetName || newSheetName === null) {
                    return;
                }

                this.trigger("rename", { name: newSheetName, sheetIndex: this._selectedIndex });
            },

            _onAddSelect: function(ev) {
                ev.sender.element.removeClass("k-focus");
                this.trigger("select", { isAddButton: true });
            },

            _onMenuSelect: function(ev) {
                let sheetName = $(ev.target).closest("li").data("sheetName");
                if (sheetName) {
                    this.trigger("show", { name: sheetName });
                }
            },

            _addButton: function() {
                var classNames = SheetsBar.classNames;
                let addButton = this.element.find("." + classNames.sheetsBarAdd);
                if (!addButton.length) {
                    addButton = $(`<button class="${classNames.sheetsBarAdd}" aria-label="Add new sheet"></button>`).appendTo(this.element);
                } else {
                    kendo.destroy(addButton);
                }

                addButton.kendoButton({
                    icon: classNames.sheetsBarPlusIcon,
                    fillMode: "flat",
                    click: this._onAddSelect.bind(this)
                });
            },
            _menuButton: function() {
                var classNames = SheetsBar.classNames;
                let menuButton = this.element.find("." + classNames.sheetsBarSheetsMenu);
                if (!menuButton.length) {
                    menuButton = $(`<button class="${classNames.sheetsBarSheetsMenu}"></button>`).appendTo(this.element);
                } else {
                    kendo.destroy(menuButton);
                }

                menuButton.kendoDropDownButton({
                    icon: classNames.sheetsBarMenuIcon,
                    fillMode: "flat",
                    items: (this._sheets || []).map(sheet => ({
                        text: sheet.name(),
                        attributes: { 'data-sheet-name': sheet.name() },
                        icon: sheet.state() === 'visible' ? 'eye' : 'eye-slash',
                        cssClass: classNames
                    })),
                    click: this._onMenuSelect.bind(this)
                });
            },

            destroy: function() {
                this._sortable.destroy();
            },

            _scrollableAllowed: function() {
                var options = this.options;
                return options.scrollable && !isNaN(options.scrollable.distance);
            },

            _scrollSheetsToItem: function(item) {
                var that = this;
                var sheetsGroup = that._sheetsGroup();
                var currentScrollOffset = kendo.scrollLeft(sheetsGroup);
                var itemWidth = outerWidth(item);
                var itemOffset = that._isRtl ? item.position().left : item.position().left - sheetsGroup.children().first().position().left;
                var sheetsGroupWidth = sheetsGroup[0].offsetWidth;
                var itemPosition;

                if (that._isRtl) {
                    if (itemOffset < 0) {
                        itemPosition = currentScrollOffset + itemOffset - (sheetsGroupWidth - currentScrollOffset);
                    } else if (itemOffset + itemWidth > sheetsGroupWidth) {
                        itemPosition = currentScrollOffset + itemOffset - itemWidth;
                    }
                } else {
                    if (currentScrollOffset + sheetsGroupWidth < itemOffset + itemWidth) {
                        itemPosition = itemOffset + itemWidth - sheetsGroupWidth;
                    } else if (currentScrollOffset > itemOffset) {
                        itemPosition = itemOffset;
                    }
                }

                sheetsGroup.finish().animate({ "scrollLeft": itemPosition }, "fast", "linear", function() {
                    that._toggleScrollButtons();
                });
            },

            _sheetsGroup: function() {
                return this._sheetsWrapper().find("ul");
            },

            _sheetsWrapper: function() {
                return this.element.find(DOT + SheetsBar.classNames.sheetsBarItems);
            },

            _scrollSheetsByDelta: function(delta) {
                var that = this;
                var sheetsGroup = that._sheetsGroup();
                var scrLeft = kendo.scrollLeft(sheetsGroup);

                sheetsGroup.finish().animate({ "scrollLeft": scrLeft + delta }, "fast", "linear", function() {
                    if (that._nowScrollingSheets) {
                        that._scrollSheetsByDelta(delta);
                    } else {
                        that._toggleScrollButtons();
                    }
                });
            }
        });

        kendo.spreadsheet.SheetsBar = SheetsBar;
        $.extend(true, SheetsBar, { classNames: sheetsBarClassNames });
    })(window.kendo);
