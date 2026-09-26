output "container_name" {
  value = incus_instance.student_lab.name
}

output "container_ipv4" {
  value = incus_instance.student_lab.ipv4_address
}

output "container_status" {
  value = incus_instance.student_lab.status
}
