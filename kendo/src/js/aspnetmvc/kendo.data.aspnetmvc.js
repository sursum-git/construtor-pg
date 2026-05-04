/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

import "../kendo.data.js";
import "../kendo.combobox.js";
import "../kendo.multiselect.js";
import "../kendo.validator.js";

(function($, undefined) {
    var kendo = window.kendo,
        escapeQuoteRegExp = /'/ig,
        extend = $.extend,
        isArray = Array.isArray,
        isPlainObject = $.isPlainObject,
        POINT = ".";

    function parameterMap(options, operation, serializationOptions) {
       var result = {};

       if (options.sort) {
           result[this.options.prefix + "sort"] = $.map(options.sort, function(sort) {
               return sort.field + "-" + sort.dir;
           }).join("~");

           delete options.sort;
       } else {
           result[this.options.prefix + "sort"] = "";
       }

       if (options.page) {
           result[this.options.prefix + "page"] = options.page;

           delete options.page;
       }

       if (options.pageSize) {
           result[this.options.prefix + "pageSize"] = options.pageSize;

           delete options.pageSize;
       }

       if (options.group) {
            result[this.options.prefix + "group"] = $.map(options.group, function(group) {
               return group.field + "-" + group.dir;
           }).join("~");

           delete options.group;
       } else {
            result[this.options.prefix + "group"] = "";
       }

       if (options.aggregate) {
           result[this.options.prefix + "aggregate"] = $.map(options.aggregate, function(aggregate) {
               return aggregate.field + "-" + aggregate.aggregate;
           }).join("~");

           delete options.aggregate;
       }

       if (options.filter) {
           result[this.options.prefix + "filter"] = serializeFilter(options.filter, serializationOptions.encode);

            if (serializationOptions.caseSensitiveFilter) {
                result["caseSensitiveFilter"] = true;
            }

           delete options.filter;
       } else {
           result[this.options.prefix + "filter"] = "";
           delete options.filter;
       }

       if (!options.groupPaging) {
           delete options.take;
           delete options.skip;
       }

       var serializer = new Serializer(serializationOptions);
       serializer.serialize(result, options, "");

       return result;
    }

    var Serializer = function(options) {
        options = options || {};
        this.culture = options.culture || kendo.culture();
        this.stringifyDates = options.stringifyDates;
        this.decimalSeparator = this.culture.numberFormat[POINT];
    };

    Serializer.prototype = Serializer.fn = {
        serialize: function(result, data, prefix) {
            var valuePrefix;
            for (var key in data) {
                valuePrefix = prefix ? prefix + "." + key : key;
                this.serializeField(result, data[key], data, key, valuePrefix);
            }
        },

        serializeField: function(result, value, data, key, prefix) {
            if (isArray(value)) {
                this.serializeArray(result, value, prefix);
            } else if (isPlainObject(value)) {
                this.serialize(result, value, prefix);
            } else if (result[prefix] === undefined) {
                result[prefix] = data[key] = this.serializeValue(value);
            }
        },

        serializeArray: function(result, data, prefix) {
            var value, key, valuePrefix;
            for (var sourceIndex = 0, destinationIndex = 0; sourceIndex < data.length; sourceIndex++) {
                value = data[sourceIndex];
                key = "[" + destinationIndex + "]";
                valuePrefix = prefix + key;

                this.serializeField(result, value, data, key, valuePrefix);

                destinationIndex++;
            }
        },

        serializeValue: function(value) {
            if (value instanceof Date) {
                if (this.stringifyDates) {
                    value = kendo.stringify(value).replace(/"/g, "");
                } else {
                    value = kendo.toString(value, "G", this.culture.name);
                }
            } else if (typeof value === "number") {
                value = value.toString().replace(POINT, this.decimalSeparator);
            }
            return value;
        }
    };

    function serializeFilter(filter, encode) {
       if (filter.filters) {
           return $.map(filter.filters, function(f) {
               var hasChildren = f.filters && f.filters.length > 1,
                   result = serializeFilter(f, encode);

               if (result && hasChildren) {
                   result = "(" + result + ")";
               }

               return result;
           }).join("~" + filter.logic + "~");
       }

       if (filter.field) {
           return filter.field + "~" + filter.operator + "~" + encodeFilterValue(filter.value, encode);
       } else {
           return undefined;
       }
    }

    function encodeFilterValue(value, encode) {
       if (typeof value === "string") {
           if (value.indexOf('Date(') > -1) {
               value = new Date(parseInt(value.replace(/^\/Date\((.*?)\)\/$/, '$1'), 10));
           } else {
               value = value.replace(escapeQuoteRegExp, "''");

               if (encode) {
                   value = encodeURIComponent(value);
               }

               return "'" + value + "'";
           }
       }

       if (value && value.getTime) {
           return "datetime'" + kendo.format("{0:yyyy-MM-ddTHH-mm-ss}", value) + "'";
       }
       return value;
    }

    function valueOrDefault(value, defaultValue) {
        return typeof value !== "undefined" ? value : defaultValue;
    }

    function translateGroup(group) {
        var hasSubgroups = group.HasSubgroups || group.hasSubgroups || false;
        var items = group.Items || group.items;
        var itemCount = group.ItemCount || group.itemCount;
        var subgroupCount = group.SubgroupCount || group.subgroupCount;

        return {
            value: valueOrDefault(group.Key, valueOrDefault(group.key, group.value)),
            field: group.Member || group.member || group.field,
            hasSubgroups: hasSubgroups,
            aggregates: translateAggregate(group.Aggregates || group.aggregates),
            items: hasSubgroups ? $.map(items, translateGroup) : items,
            itemCount: itemCount,
            subgroupCount: subgroupCount,
            uid: kendo.guid()
        };
    }

    function translateAggregateResults(aggregate) {
       var obj = {};
       obj[(aggregate.AggregateMethodName || aggregate.aggregateMethodName).toLowerCase()] = valueOrDefault(aggregate.Value, aggregate.value);

       return obj;
    }

    function translateAggregate(aggregates) {
        var functionResult = {},
            key,
            functionName,
            aggregate;

        for (key in aggregates) {
            functionResult = {};
            aggregate = aggregates[key];

            for (functionName in aggregate) {
               functionResult[functionName.toLowerCase()] = aggregate[functionName];
            }

            aggregates[key] = functionResult;
        }

        return aggregates;
    }

    function convertAggregates(aggregates) {
        var idx, length, aggregate;
        var result = {};

        for (idx = 0, length = aggregates.length; idx < length; idx++) {
            aggregate = aggregates[idx];
            result[(aggregate.Member || aggregate.member)] = extend(true, result[(aggregate.Member || aggregate.member)], translateAggregateResults(aggregate));
        }

        return result;
    }

    extend(true, kendo.data, {
        schemas: {
            "aspnetmvc-ajax": {
                groups: function(data) {
                    return $.map(this._dataAccessFunction(data), translateGroup);
                },
                aggregates: function(data) {
                    data = data.d || data;
                    var aggregates = data.AggregateResults || data.aggregateResults || [];

                    if (!Array.isArray(aggregates)) {
                        for (var key in aggregates) {
                            aggregates[key] = convertAggregates(aggregates[key]);
                        }

                        return aggregates;
                    }

                    return convertAggregates(aggregates);
                }
            }
        }
    });

    extend(true, kendo.data, {
        transports: {
            "aspnetmvc-ajax": kendo.data.RemoteTransport.extend({
                init: function(options) {
                    var that = this,
                        stringifyDates = (options || {}).stringifyDates,
                        caseSensitiveFilter = (options || {}).caseSensitiveFilter;

                    kendo.data.RemoteTransport.fn.init.call(this,
                        extend(true, {}, this.options, options, {
                            parameterMap: function(options, operation) {
                                return parameterMap.call(that, options, operation, {
                                    encode: false,
                                    stringifyDates: stringifyDates,
                                    caseSensitiveFilter: caseSensitiveFilter
                                });
                            }
                        })
                    );
                },
                read: function(options) {
                    var data = this.options.data,
                        url = this.options.read.url;

                    if (isPlainObject(data)) {
                        if (url) {
                            this.options.data = null;
                        }

                        if (!data.Data.length && url) {
                            kendo.data.RemoteTransport.fn.read.call(this, options);
                        } else {
                            options.success(data);
                        }
                    } else {
                        kendo.data.RemoteTransport.fn.read.call(this, options);
                    }
                },
                options: {
                    read: {
                        type: "POST"
                    },
                    update: {
                        type: "POST"
                    },
                    create: {
                        type: "POST"
                    },
                    destroy: {
                        type: "POST"
                    },
                    parameterMap: parameterMap,
                    prefix: ""
                }
            })
        }
    });

    extend(true, kendo.data, {
       schemas: {
           "webapi": kendo.data.schemas["aspnetmvc-ajax"]
       }
    });

    extend(true, kendo.data, {
        transports: {
            "webapi": kendo.data.RemoteTransport.extend({
                init: function(options) {
                    var that = this;
                    var stringifyDates = (options || {}).stringifyDates;
                    var culture = kendo.cultures[options.culture] || kendo.cultures["en-US"];

                    if (options.update) {
                        var updateUrl = typeof options.update === "string" ? options.update : options.update.url;

                        options.update = extend(options.update, { url: function(data) {
                            return kendo.format(updateUrl, data[options.idField]);
                        } });
                    }

                    if (options.destroy) {
                        var destroyUrl = typeof options.destroy === "string" ? options.destroy : options.destroy.url;

                        options.destroy = extend(options.destroy, { url: function(data) {
                            return kendo.format(destroyUrl, data[options.idField]);
                        } });
                    }

                    if (options.create && typeof options.create === "string") {
                        options.create = {
                            url: options.create
                        };
                    }

                    kendo.data.RemoteTransport.fn.init.call(this,
                        extend(true, {}, this.options, options, {
                            parameterMap: function(options, operation) {
                                return parameterMap.call(that, options, operation, {
                                    encode: false,
                                    stringifyDates: stringifyDates,
                                    culture: culture
                                });
                            }
                        })
                    );
                },
                read: function(options) {
                    var data = this.options.data,
                        url = this.options.read.url;

                    if (isPlainObject(data)) {
                        if (url) {
                            this.options.data = null;
                        }

                        if (!data.Data.length && url) {
                            kendo.data.RemoteTransport.fn.read.call(this, options);
                        } else {
                            options.success(data);
                        }
                    } else {
                        kendo.data.RemoteTransport.fn.read.call(this, options);
                    }
                },
                options: {
                    read: {
                        type: "GET"
                    },
                    update: {
                        type: "PUT"
                    },
                    create: {
                        type: "POST"
                    },
                    destroy: {
                        type: "DELETE"
                    },
                    parameterMap: parameterMap,
                    prefix: ""
                }
            })
        }
    });

    extend(true, kendo.data, {
        transports: {
            "aspnetmvc-server": kendo.data.RemoteTransport.extend({
                init: function(options) {
                    var that = this;

                    kendo.data.RemoteTransport.fn.init.call(this,
                        extend(options, {
                            parameterMap: function(options, operation) {
                                return parameterMap.call(that, options, operation, {
                                    encode: true
                                });
                            }
                        }
                    ));
                },
                read: function(options) {
                    var url,
                        prefix = this.options.prefix,
                        params = [prefix + "sort",
                            prefix + "page",
                            prefix + "pageSize",
                            prefix + "group",
                            prefix + "aggregate",
                            prefix + "filter"],
                        regExp = new RegExp("(" + params.join('|') + ")=[^&]*&?", "g"),
                        query;

                    query = location.search.replace(regExp, "").replace("?", "");

                    if (query.length && !(/&$/.test(query))) {
                        query += "&";
                    }

                    options = this.setup(options, "read");

                    url = options.url;

                    if (url.indexOf("?") >= 0) {
                        query = query.replace(/(.*?=.*?)&/g, function(match) {
                            if (url.indexOf(match.substr(0, match.indexOf("="))) >= 0) {
                               return "";
                            }
                            return match;
                        });
                        url += "&" + query;
                    } else {
                        url += "?" + query;
                    }

                    url += $.map(options.data, function(value, key) {
                        return key + "=" + value;
                    }).join("&");

                    location.href = url;
                }
            })
        }
    });
})(window.kendo.jQuery);
