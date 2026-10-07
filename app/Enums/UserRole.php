<?php

namespace App\Enums;

/**
 * What a person may do in the portal.
 *
 * ── On the lecturer role ───────────────────────────────────────────────────────────
 *
 * Added for the administrative console. The brief is that a lecturer may open a shell on
 * the infrastructure nodes the way an administrator does, while a student's terminal
 * stays strictly inside their own lab environment — a different capability, not a
 * different privilege level within one. So the split is at the role: anything reaching
 * the infrastructure needs one of the two elevated roles, and nothing about a student
 * account can be configured to grant it.
 *
 * Adding a case to this enum is backwards compatible. Every existing row carries 'admin'
 * or 'student' as a string, and neither is renamed, so no data moves.
 */
enum UserRole: string
{
    /** Full access, including the administrative console. */
    case Admin = 'admin';

    /** May open the administrative console. Cannot manage users or platform settings. */
    case Lecturer = 'lecturer';

    /** Confined to their own lab environments and their own terminal. */
    case Student = 'student';

    /**
     * Whether this role may reach the infrastructure nodes.
     *
     * Asked by the terminal service and by the page that offers the target list, so the
     * answer cannot depend on which of the two the caller happened to remember to check.
     * A shell on a node is root-adjacent by definition — it is the account that owns the
     * portal — so it is never something a student role can be talked into.
     */
    public function canOperateInfrastructure(): bool
    {
        return $this === self::Admin || $this === self::Lecturer;
    }

    /**
     * Whether this role may administer the portal itself.
     *
     * Narrower than canOperateInfrastructure on purpose: opening a shell is a capability
     * a lecturer needs to run a lab, while managing accounts and platform settings is
     * not theirs to do.
     */
    public function canAdminister(): bool
    {
        return $this === self::Admin;
    }
}