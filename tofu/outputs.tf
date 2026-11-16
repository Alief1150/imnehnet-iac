output "container_name" {
  value = incus_instance.student_lab.name
}

output "container_ipv4" {
  value = incus_instance.student_lab.ipv4_address
}

output "container_status" {
  value = incus_instance.student_lab.status
}

# Which remote the instance landed on.
#
# Recorded because a lab is a pairing across two planes, and reconciling a session
# means knowing which plane a container is on without having to remember which half of
# the pair it was meant to be. Read from the variable rather than from the instance
# because placement is an input, not something Incus reports back.
#
# The three outputs above are what the portal reads to learn a container's address, and
# they were briefly lost when this file was rewritten to add this one. `tofu output`
# then answered "Output not found" for all of them, which is the shape of a portal that
# has stopped being able to discover where it just created something.
output "container_remote" {
  value = var.incus_remote
}