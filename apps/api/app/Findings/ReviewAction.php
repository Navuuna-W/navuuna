<?php

// The buttons on the review screen (ADR-010 DEC-10, ADR-013). The review endpoint receives one
// of these as `action`; ReviewFlag turns it into FlagWorkflow moves.

declare(strict_types=1);

namespace App\Findings;

/**
 * What an analyst or admin decided about one finding. Implements ADR-010 DEC-10 and ADR-013.
 */
enum ReviewAction: string
{
    /** held → explanation_checked ("none found") → published, in one request. */
    case Publish = 'publish';

    /** held → explanation_checked (the explanation given) → dismissed, in one request. */
    case Dismiss = 'dismiss';

    /** Stays held; only a `note_added` audit event. */
    case NeedsMoreEvidence = 'needs_more_evidence';

    /** published → contested: the named party disputes the finding. */
    case Contest = 'contest';

    /** contested → resolved: we agree with the contest. */
    case Resolve = 'resolve';

    /** contested → published: we reject the contest and the finding stands. */
    case RejectContest = 'reject_contest';
}
