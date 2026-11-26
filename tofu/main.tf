# The lab is a pair: a victim on lab-env and an attacker on lab-atk.
#
# The remote was a literal, which meant this module could only ever create a victim.
# It is a variable now, so the same module creates both halves.
#
# The interface is declared as a device rather than left to the profile, because the
# lab's identity is the pairing of two addresses and the portal has to be able to
# state the target before the student runs anything. Left to the hypervisor, the
# address is unknowable until after provisioning, which is the one moment it is needed.
#
# security.acls is applied to the attacker only. An ACL is what refuses one student a
# route to another student's victim, and the restriction belongs on the side that
# initiates the connection. LAB-ATK runs Incus 7.0.1 and supports ACLs; LAB-ENV runs
# 6.0.0 and does not, so the key is omitted entirely for a victim rather than set to
# an empty string, which Incus rejects as a malformed value rather than ignoring.

resource "incus_instance" "student_lab" {
  name     = var.container_name
  image    = var.container_image
  type     = "container"
  remote   = var.incus_remote
  profiles = ["default"]

  # The root device is overridden only for a non-default pool. The default
  # profile continues to supply its original root disk for legacy containers.
  dynamic "device" {
    for_each = var.storage_pool == "default" ? [] : [var.storage_pool]

    content {
      name = "root"
      type = "disk"
      properties = {
        path = "/"
        pool = device.value
      }
    }
  }

  # Start and stop are expressed as a desired state rather than as a separate
  # command, so the lifecycle is declarative: setting this to false and applying
  # converges the instance to stopped exactly as setting it to true converges it to
  # running.
  running = var.desired_running

  config = {
    "boot.autostart" = "true"
    "limits.cpu"     = tostring(var.cpu_limit)
    "limits.memory"  = var.memory_limit
  }

  # Declared only when an address was asked for. Adding the device with no address
  # would hand the interface to Incus, which is what the implicit device in the
  # profile already does, and would override any address the profile had set.
  dynamic "device" {
    for_each = var.static_ipv4 == null ? [] : [1]

    content {
      name = "eth0"
      type = "nic"

      properties = merge({
        network        = var.incus_network
        "ipv4.address" = var.static_ipv4
        }, var.security_acls == "" ? {} : {
        "security.acls" = var.security_acls
      })
    }
  }

  wait_for {
    type = "ipv4"
    nic  = "eth0"
  }
}