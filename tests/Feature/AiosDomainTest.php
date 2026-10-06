<?php

namespace Tests\Feature;

use App\Enums\AgentStatus;
use App\Enums\ApprovalStatus;
use App\Enums\ArtifactRelation;
use App\Enums\CommandStatus;
use App\Enums\ProjectStatus;
use App\Enums\RoomStatus;
use App\Models\Agent;
use App\Models\Approval;
use App\Models\Artifact;
use App\Models\ArtifactLink;
use App\Models\AuditLog;
use App\Models\CommandHistory;
use App\Models\Cost;
use App\Models\Project;
use App\Models\Role;
use App\Models\Room;
use App\Models\Secret;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiosDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_agent_artifact_chain_with_relations_and_scopes(): void
    {
        $owner = User::factory()->create();
        $room = Room::create([
            'number' => '01',
            'name' => 'IT Team',
            'status' => RoomStatus::Active,
            'owner_id' => $owner->id,
        ]);

        $role = Role::create([
            'slug' => 'backend-dev',
            'name' => 'Backend Dev',
            'instructions' => 'Build APIs.',
            'default_combo' => 'combo-coding',
        ]);

        $agent = Agent::create([
            'slug' => 'backend-dev-1',
            'name' => 'Backend Dev 1',
            'room_id' => $room->id,
            'role_id' => $role->id,
            'combo_name' => 'combo-coding',
        ]);

        $project = Project::create([
            'slug' => 'inventaris',
            'name' => 'Inventaris Gudang',
            'room_id' => $room->id,
            'status' => ProjectStatus::Running,
            'owner_id' => $owner->id,
        ]);

        $prd = Artifact::create([
            'project_id' => $project->id,
            'room_id' => $room->id,
            'type' => 'prd',
            'title' => 'PRD',
            'author_agent_id' => $agent->id,
        ]);
        $schema = Artifact::create([
            'project_id' => $project->id,
            'room_id' => $room->id,
            'type' => 'schema',
            'title' => 'DB Schema',
        ]);
        ArtifactLink::create([
            'from_artifact_id' => $prd->id,
            'to_artifact_id' => $schema->id,
            'relation' => ArtifactRelation::DerivedFrom,
        ]);

        Approval::create([
            'project_id' => $project->id,
            'stage' => 'prd',
            'artifact_id' => $prd->id,
            'requested_by' => $owner->id,
        ]);

        Cost::create([
            'project_id' => $project->id,
            'room_id' => $room->id,
            'agent_id' => $agent->id,
            'stage' => 'prd',
            'model' => 'combo-coding',
            'tokens_in' => 100,
            'tokens_out' => 50,
        ]);

        CommandHistory::create([
            'user_id' => $owner->id,
            'raw_input' => 'room show 01',
            'status' => CommandStatus::Executed,
        ]);

        AuditLog::create([
            'actor_type' => 'user',
            'actor_id' => $owner->id,
            'action' => 'project.run',
            'target_type' => 'project',
            'target_id' => $project->id,
        ]);

        Secret::create(['scope' => 'global', 'key_name' => 'ninerouter', 'value' => 's3cr3t']);

        // Relations
        $this->assertSame('IT Team', $agent->room->name);
        $this->assertSame('Backend Dev', $agent->role->name);
        $this->assertCount(1, $room->agents);
        $this->assertCount(2, $project->artifacts);
        $this->assertCount(1, $prd->outgoingLinks);
        $this->assertCount(1, $schema->incomingLinks);
        $this->assertSame(ArtifactRelation::DerivedFrom, $prd->outgoingLinks->first()->relation);

        // Scopes + enum casts
        $this->assertSame(1, Room::active()->count());
        $this->assertSame(1, $room->agents()->active()->count());
        $this->assertSame(1, Project::running()->count());
        $this->assertSame(1, $project->approvals()->pending()->count());
        $this->assertSame(ApprovalStatus::Pending, $project->approvals->first()->status);
        $this->assertSame(AgentStatus::Active, $agent->status);

        // Encrypted secret round-trips, hidden from serialization
        $this->assertSame('s3cr3t', Secret::first()->value);
        $this->assertArrayNotHasKey('value', Secret::first()->toArray());

        // Audit log has no updated_at
        $this->assertNull(AuditLog::first()->updated_at);

        // Soft delete keeps history, hides from default queries
        $agent->delete();
        $this->assertSame(0, Agent::count());
        $this->assertSame(1, Agent::withTrashed()->count());

        // Room delete cascades project data
        $room->delete();
        $this->assertSame(0, Room::where('number', '01')->count());
        $this->assertSame(0, Project::count());
        $this->assertSame(0, Artifact::count());
        $this->assertSame(0, Cost::count());
    }
}
