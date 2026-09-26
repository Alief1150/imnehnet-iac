provider "incus" {
  config_dir     = "/home/iaas/.config/incus"
  default_remote = "lab-env"

  remote {
    name    = "lab-env"
    address = "https://192.168.100.20:8443"
  }
}
