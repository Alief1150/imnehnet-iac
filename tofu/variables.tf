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
