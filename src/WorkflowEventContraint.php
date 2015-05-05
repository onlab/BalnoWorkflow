<?php

namespace BalnoWorkflow;

interface WorkflowEventContraint
{
    const BEGIN_EXECUTION = 'begin_execution';
    const END_EXECUTION = 'end_execution';

    const BEGIN_TRANSITION = 'start_transition';
    const END_TRANSITION = 'end_transition';

    const STATE_CHANGED = 'state_changed';
}
