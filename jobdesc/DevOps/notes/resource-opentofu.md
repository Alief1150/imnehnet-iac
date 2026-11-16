iaas@iaas-mgmt:~/projects/webportal-iaas$ cd /home/iaas/imnehnet-iac/tofu

echo "========== OPENTOFU RESOURCES =========="

find . -maxdepth 3 -type f -name '*.tf' \
  -print0 | xargs -0 -r grep -nE \
  '^[[:space:]]*(terraform|provider|resource|variable|output|module)[[:space:]]'

echo
echo "========== WORKSPACES =========="
tofu workspace list

echo
echo "========== MANAGED RESOURCES =========="
tofu state list
========== OPENTOFU RESOURCES ==========
./outputs.tf:1:output "container_name" {
./outputs.tf:5:output "container_ipv4" {
./outputs.tf:9:output "container_status" {
./outputs.tf:24:output "container_remote" {
./variables.tf:1:variable "container_name" {
./variables.tf:6:variable "container_image" {
./variables.tf:16:variable "incus_remote" {
./variables.tf:32:variable "incus_network" {
./variables.tf:43:variable "static_ipv4" {
./variables.tf:55:variable "security_acls" {
./variables.tf:61:variable "cpu_limit" {
./variables.tf:66:variable "memory_limit" {
./variables.tf:71:variable "desired_running" {
./version.tf:1:terraform {
./provider.tf:10:provider "incus" {
./main.tf:17:resource "incus_instance" "student_lab" {

========== WORKSPACES ==========
  default
  attacker-106
  attacker-107
  attacker-109
  ctf-attacker-102
  ctf-attacker-103
* ctf-attacker-104
  ctf-attacker-106
  ctf-attacker-107
  ctf-attacker-109
  ctf-victim-102
  ctf-victim-103
  ctf-victim-104
  ctf-victim-106
  ctf-victim-107
  ctf-victim-109
  env-lab-web-smoke


========== MANAGED RESOURCES ==========
incus_instance.student_lab
