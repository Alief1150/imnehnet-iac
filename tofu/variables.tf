variable "container_name" {
  type    = string
  default = "lab-tofu-test"
}

variable "container_image" {
  type    = string
  default = "images:ubuntu/24.04"
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
