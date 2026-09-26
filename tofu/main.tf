resource "incus_instance" "student_lab" {
  name     = var.container_name
  image    = var.container_image
  type     = "container"
  remote   = "lab-env"
  profiles = ["default"]

  running = true

  config = {
    "boot.autostart" = "true"
    "limits.cpu"     = tostring(var.cpu_limit)
    "limits.memory"  = var.memory_limit
  }

  wait_for {
    type = "ipv4"
    nic  = "eth0"
  }
}
