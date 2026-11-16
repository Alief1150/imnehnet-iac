# Both compute planes, because a lab is a pair.
#
# lab-env holds the victim, lab-atk holds the attacker. With only lab-env declared the
# module could create one half of a lab and never the other, which is what happened
# before: the attacker plane was unreachable over the API and nothing in the
# configuration expressed that it was needed.
#
# default_remote stays lab-env so a plan with no -var against the existing reference
# environment produces no diff.
provider "incus" {
  config_dir     = "/home/iaas/.config/incus"
  default_remote = "lab-env"

  remote {
    name    = "lab-env"
    address = "https://192.168.100.20:8443"
  }

  remote {
    name    = "lab-atk"
    address = "https://192.168.100.30:8443"
  }
}