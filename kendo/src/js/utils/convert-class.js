/**
 * Kendo UI v2026.1.212 (http://www.telerik.com/kendo-ui)
 * Copyright 2026 Progress Software Corporation and/or one of its subsidiaries or affiliates. All rights reserved.
 *
 * Kendo UI commercial licenses may be obtained at
 * http://www.telerik.com/purchase/license-agreement/kendo-ui-complete
 * If you do not own a commercial license, this file shall be governed by the trial license terms.
 */

export function fromESClass(ESClass) {
    class ExtendedClass extends ESClass {
        static extend(proto) {
            const subclass = class extends ExtendedClass {
                constructor() {
                    super();
                    if (proto && proto.init) {
                        proto.init.apply(this, arguments);
                    }
                }
            };

            // Copy the prototype so that the constructor is not overwritten
            Object.assign(subclass.prototype, proto);

            addInstanceGetter(subclass.prototype);

            // Apply the prototype to fn to allow for chaining
            subclass.fn = subclass.prototype;

            return subclass;
        }
    }

    addInstanceGetter(ExtendedClass.prototype);

    // Apply the prototype to fn to allow for chaining
    ExtendedClass.fn = ExtendedClass.prototype;

    return ExtendedClass;
}

function addInstanceGetter(proto) {
    Object.defineProperty(proto, '_instance', {
        get: function() {
            return this;
        }
    });
}