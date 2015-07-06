<?php

namespace BalnoWorkflow;

interface WorkflowEvents
{
    const BEGIN_EXECUTION = 'begin_execution';
    const END_EXECUTION = 'end_execution';

    const BEGIN_TRANSITION = 'begin_transition';
    const END_TRANSITION = 'end_transition';

    const STATE_CHANGED = 'state_changed';

    const ON_ACTION_ERROR = 'on_action_error';
    const ON_GUARD_ERROR = 'on_guard_error';
}
