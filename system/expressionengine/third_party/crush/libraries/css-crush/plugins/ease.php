<?php
/**
 * Expanded easing keywords for transitions
 *
 * @see docs/plugins/ease.md
 */
namespace CssCrush;

Plugin::register('ease', array(
    'enable' => function ($process) {
        $process->hooks->add('rule_prealias', 'CssCrush\ease');
    }
));


function ease(Rule $rule) {

    static $find, $replace, $easing_properties;
    if (! $find) {
        $easings = array(
            'ease-in-out-back' => 'cubic-bezier(.680,-0.550,.265,1.550)',
            'ease-in-out-circ' => 'cubic-bezier(.785,.135,.150,.860)',
            'ease-in-out-expo' => 'cubic-bezier(1,0,0,1)',
            'ease-in-out-sine' => 'cubic-bezier(.445,.050,.550,.950)',
            'ease-in-out-quint' => 'cubic-bezier(.860,0,.070,1)',
            'ease-in-out-quart' => 'cubic-bezier(.770,0,.175,1)',
            'ease-in-out-cubic' => 'cubic-bezier(.645,.045,.355,1)',
            'ease-in-out-quad' => 'cubic-bezier(.455,.030,.515,.955)',
            'ease-out-back' => 'cubic-bezier(.175,.885,.320,1.275)',
            'ease-out-circ' => 'cubic-bezier(.075,.820,.165,1)',
            'ease-out-expo' => 'cubic-bezier(.190,1,.220,1)',
            'ease-out-sine' => 'cubic-bezier(.390,.575,.565,1)',
            'ease-out-quint' => 'cubic-bezier(.230,1,.320,1)',
            'ease-out-quart' => 'cubic-bezier(.165,.840,.440,1)',
            'ease-out-cubic' => 'cubic-bezier(.215,.610,.355,1)',
            'ease-out-quad' => 'cubic-bezier(.250,.460,.450,.940)',
            'ease-in-back' => 'cubic-bezier(.600,-0.280,.735,.045)',
            'ease-in-circ' => 'cubic-bezier(.600,.040,.980,.335)',
            'ease-in-expo' => 'cubic-bezier(.950,.050,.795,.035)',
            'ease-in-sine' => 'cubic-bezier(.470,0,.745,.715)',
            'ease-in-quint' => 'cubic-bezier(.755,.050,.855,.060)',
            'ease-in-quart' => 'cubic-bezier(.895,.030,.685,.220)',
            'ease-in-cubic' => 'cubic-bezier(.550,.055,.675,.190)',
            'ease-in-quad' => 'cubic-bezier(.550,.085,.680,.530)',
        );

        $easing_properties = array(
            'transition' => true,
            'transition-timing-function' => true,
        );

        foreach ($easings as $property => $value) {
            $patt = Regex::make("~{{ LB }}$property{{ RB }}~i");
            $find[] = $patt;
            $replace[] = $value;
        }
    }

    if (! array_intersect_key($rule->declarations->canonicalProperties, $easing_properties)) {
        return;
    }

    foreach ($rule->declarations->filter(array('skip' => false)) as $declaration) {
        if (isset($easing_properties[$declaration->canonicalProperty])) {
            $declaration->value = preg_replace($find, $replace, $declaration->value);
        }
    }
}
