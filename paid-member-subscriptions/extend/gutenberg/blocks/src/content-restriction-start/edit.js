/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from "@wordpress/i18n";

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps } from "@wordpress/block-editor";
import { InspectorControls } from "@wordpress/block-editor";
import { PanelBody } from "@wordpress/components";

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import "./editor.scss";
import PMSBlockContentRestrictionControlsCommon from "../../../block-content-restriction/src/controls.js";

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {WPElement} Element to render.
 */
const Edit = (props) => {
    if (props.attributes.cover) {
        return (
            <svg>
                <path d="m 108.58991,71.860054 c -0.12642,6.735659 -10.801799,2.313778 -6.12836,-2.538448 2.04279,-2.308224 6.31606,-0.538187 6.12836,2.538448 z m 0,-16.249523 c -0.12642,6.735659 -10.801799,2.313778 -6.12836,-2.538448 2.04279,-2.308224 6.31606,-0.538187 6.12836,2.538448 z m 0,32.499042 c 0.0736,6.378054 -10.857794,2.778784 -6.12836,-2.538448 2.04279,-2.308224 6.31606,-0.538187 6.12836,2.538448 z M 40.5776,27.950914 c 43.365053,0.02569 86.73169,-0.0514 130.09575,0.03858 5.16876,-0.211654 13.67069,4.315003 9.99437,10.386186 -4.8314,5.966384 -13.09809,4.323603 -19.87616,4.575234 -40.48811,-0.02568 -80.977797,0.05139 -121.464912,-0.03858 -5.168763,0.211654 -13.670692,-4.315003 -9.99437,-10.386186 2.540507,-3.432073 7.194037,-4.528697 11.245322,-4.575234 z"></path>
            </svg>
        );
    } else {
        return (
            <>
                <InspectorControls>
                    <PanelBody>
                        <PMSBlockContentRestrictionControlsCommon {...props} />
                    </PanelBody>
                </InspectorControls>
                <div {...useBlockProps()}>
                    <div className="pms-content-restriction-start-container">
                        <div className="pms-content-restriction-start-logo">
                            <svg
                                width="24"
                                height="24"
                                aria-hidden="true"
                                focusable="false"
                            >
                                <path d="m 14.942341,7.4403325 -0.0646,10.0257505 h 2.5709 v -0.96428 h -1.54254 V 8.4681825 h 1.60713 v -1.02785 z M 8.2967536,0.00875251 c -0.180899,0 -0.529584,0.0503 -0.711584,0.0863 -0.4916,0.0983 -0.968034,0.26298 -1.409733,0.50177 -0.5554,0.3004 -0.9937526,0.66592999 -1.4226516,1.12602999 -0.3359,0.3603 -0.613971,0.80981 -0.815971,1.25781 -0.2172,0.4815 -0.340224,0.99994 -0.392224,1.52394 l -0.02429,0.26665 v 0.20309 l -0.01189,0.12712 0.01292,0.19017 c 0,0.2165 0.02333,0.43392 0.05633,0.64802 0.148199,0.9543 0.595754,1.8466 1.253153,2.5523 0.2333,0.2505 0.4896766,0.4814 0.7606766,0.6904 l 0.291972,0.21497 c 0.0616,0.043 0.128293,0.0782 0.17725,0.13591 -0.0765,0.0142 -0.166596,0.0559 -0.240295,0.0858 l -0.419096,0.17673 C 4.952321,9.9981626 4.51219,10.220363 4.09339,10.479963 c -0.503699,0.312 -0.944412,0.63791 -1.384411,1.03611 l -0.152446,0.14986 -0.100769,0.091 -0.09095,0.10026 -0.149345,0.15244 c -0.951099,1.0513 -1.5227431,2.17264 -1.9001431,3.53053 -0.103699,0.3728 -0.196181,0.81145 -0.252181,1.19425 l -0.05994,0.50798 c -0.003599999,0.3112 -0.01987,0.64897 0.145727,0.92707 0.2094,0.3514 0.578315,0.45213 0.9591151,0.49713 l 0.279569,0.0238 h 1.955953 10.972972 c -0.0619,-0.0794 -0.34893,-0.29339 -0.44493,-0.36639 -0.3693,-0.2796 -0.74143,-0.51524 -1.20613,-0.59324 -0.1797,-0.0302 -0.352618,-0.0312 -0.533818,-0.031 l -0.190169,0.0129 h -0.58446 l -0.19017,0.0129 h -0.965315 l -0.190686,0.0134 H 7.5216086 2.048041 l -0.216007,0.0129 -0.317294,-0.0124 c -0.134599,-0.01 -0.269655,-0.0195 -0.380855,-0.10749 -0.19200006,-0.151 -0.17421106,-0.42576 -0.14521102,-0.64286 0.0671,-0.50419 0.14985902,-0.98819 0.31005902,-1.47329 0.1897,-0.575 0.385714,-1.03586 0.690914,-1.56166 0.662599,-1.1415 1.648678,-2.11426 2.788977,-2.77606 1.8926976,-1.0984 4.2294576,-1.3420305 6.299357,-0.62373 0.3793,0.1317 0.834607,0.33978 1.180806,0.54208 l 0.330213,0.20309 0.368451,0.23823 c 0.0507,0.026 0.0835,0.0417 0.13953,0.0537 0.246,0.0541 0.39105,-0.097 0.54415,-0.26665 0.0838,-0.0926 0.17692,-0.1851 0.16692,-0.3173 -0.009,-0.1581 -0.22862,-0.21525 -0.34262,-0.28525 l -0.21601,-0.16071 c -0.420496,-0.3094 -0.919011,-0.52819 -1.39681,-0.7332905 l -0.711068,-0.29404 0.367936,-0.28474 c 0.2581,-0.2064 0.525642,-0.44798 0.747241,-0.69298 0.6521,-0.7207 1.116331,-1.54447 1.307931,-2.50217 0.037,-0.1848 0.092,-0.50195 0.092,-0.68575 v -0.15244 l 0.0119,-0.16485 -0.0119,-0.12712 v -0.15245 l -0.0481,-0.44442 c -0.0753,-0.5222 -0.23746,-1.03772 -0.47026,-1.51102 -0.206595,-0.4204 -0.478712,-0.8089 -0.792712,-1.156 l -0.226343,-0.22841 c -0.3382,-0.3382 -0.791047,-0.65312999 -1.219047,-0.86712999 -0.2155,-0.1077 -0.432425,-0.20522 -0.660425,-0.28422 -0.3987,-0.1383 -0.8367884,-0.22841 -1.2572874,-0.25941 l -0.127124,-0.0124 z m 0.06356,0.95342995 -5.16e-4,5.1e-4 h 0.0894 0.291972 l 0.152445,0.0103 c 0.3335,0.023 0.659919,0.0858 0.9777184,0.19172004 0.466501,0.1555 0.89891,0.38717 1.282609,0.69557 0.6873,0.5525 1.181301,1.29792 1.4118,2.14922 0.0638,0.2351 0.137977,0.60939 0.137977,0.85059 v 0.39378 c -5e-4,0.3357 -0.111893,0.8509 -0.224793,1.1684 -0.1869,0.5254 -0.452122,0.96582 -0.820622,1.38441 -0.333799,0.3794 -0.776649,0.71058 -1.228348,0.93638 -0.489001,0.2445 -1.0170634,0.38675 -1.5616624,0.42375 l -0.152446,0.0119 h -0.292488 l -0.126608,-0.0119 c -0.354599,-0.0244 -0.691278,-0.0921 -1.028877,-0.20464 -1.592099,-0.5307 -2.7216616,-2.01938 -2.7946616,-3.69538 l -0.01188,-0.14005 v -0.1142 l 0.01188,-0.16485 c 0.0244,-0.5587 0.171645,-1.0832 0.411345,-1.5875 l 0.193786,-0.34313 c 0.5444996,-0.8749 1.3821986,-1.50061 2.3672976,-1.79421 0.199299,-0.0596 0.441005,-0.1068 0.647505,-0.13280002 z"></path>
                            </svg>
                        </div>
                        <div className="pms-content-restriction-start-title">
                            {__("Content Restriction Start", "paid-member-subscriptions")}
                        </div>
                    </div>
                </div>
            </>
        );
    }
};

export default Edit;
