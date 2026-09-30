resource "incus_instance" "student_lab" {
  name     = var.container_name
  image    = var.container_image
  type     = "container"
  remote   = "lab-env"
  profiles = ["default"]

  # Start and stop are expressed as a desired state rather than as a separate
  # command, so the lifecycle is declarative: setting this to false and
  # applying converges the instance to stopped exactly as setting it to true
  # converges it to running.
  running = var.desired_running

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
