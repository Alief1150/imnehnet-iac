variable "container_name" {
  type    = string
  default = "lab-tofu-test"
}

variable "container_image" {
  type    = string
  default = "images:ubuntu/24.04"
}

# Always select the storage pool explicitly. Existing workspaces created
# before this input was added should continue to use the default DIR pool.
variable "storage_pool" {
  type        = string
  default     = "default"
  description = "Incus storage pool for the container root disk."

  validation {
    condition     = contains(["default", "imnehnet-btrfs-test", "imnehnet-btrfs-prod"], var.storage_pool)
    error_message = "Storage pool must be default, imnehnet-btrfs-test, or imnehnet-btrfs-prod."
  }
}

# Which Incus remote the container is created on.
#
# A variable rather than a literal because a lab is a pair and the two halves live on
# different machines: the victim on lab-env, the attacker on lab-atk. With the remote
# fixed in main.tf the same module could only ever create one of them.
variable "incus_remote" {
  type        = string
  description = "Incus remote to create the container on. lab-env for a victim, lab-atk for an attacker."
  default     = "lab-env"

  validation {
    condition     = contains(["lab-env", "lab-atk"], var.incus_remote)
    error_message = "The remote must be lab-env or lab-atk; a lab is a pair and each half has its own plane."
  }
}

# Bridge the container's interface is attached to.
#
# A variable because the two planes use different networks: the victim's bridge is
# 10.20.0.0/24 and the attacker's is 10.30.0.0/24. Hardcoding one would put half of
# every lab on the wrong subnet.
variable "incus_network" {
  type        = string
  description = "Incus bridge the container is attached to."
  default     = "incusbr0"
}

# A fixed address on that bridge, or null to let Incus allocate one.
#
# The portal pairs a victim and an attacker by last octet, so the addresses have to be
# chosen rather than discovered: 10.20.0.n on lab-env and 10.30.0.n on lab-atk for the
# same n is what makes a pair recognisable at a glance in a log.
variable "static_ipv4" {
  type        = string
  description = "Address on the bridge, or null to let Incus allocate one."
  default     = null
}

# Incus ACL applied to the container. Empty for a victim.
#
# Only the attacker carries one, because that is where the restriction on who may reach
# whom has to be enforced: an ACL denies traffic arriving from an address that is not
# part of the permitted pair. LAB-ATK supports ACLs and LAB-ENV does not, so this stays
# optional or every victim apply fails.
variable "security_acls" {
  type        = string
  description = "Incus ACL name applied to the instance. Empty means none."
  default     = ""
}

variable "cpu_limit" {
  type    = number
  default = 1
}

variable "memory_limit" {
  type    = string
  default = "1GiB"
}

variable "desired_running" {
  type        = bool
  description = "Desired runtime state. Lifecycle start and stop converge onto this."
  # True matches the state the reference environment is already in, so adding
  # this variable produces no drift for an environment created before it
  # existed.
  default = true
}