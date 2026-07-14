<?php

namespace App\Exceptions;

use App\Models\Lead;
use RuntimeException;

/**
 * Thrown by CreateLeadWithApplicationAction when lead deduplication resolves to a lead that
 * already has an application. Every application originates from a lead exactly once
 * (`applications.lead_id` is unique); inserting a second application for the same lead would
 * violate that invariant, so this is raised — inside the same transaction as the lead
 * lookup/merge — before ever attempting the insert. Any lead field changes made while
 * merging duplicate lead data roll back with it.
 */
class LeadAlreadyConvertedException extends RuntimeException
{
    public function __construct(Lead $lead)
    {
        parent::__construct(__('alerts.application.lead_already_converted', ['ref_no' => $lead->ref_no]));
    }
}
