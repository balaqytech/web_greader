<?php

namespace App\Filament\Resources\Applications\Schemas;

use App\Models\Application;
use App\States\Applications\Accepted;
use App\States\Applications\Rejected;
use App\States\Applications\UnderReview;
use App\States\Applications\WaitingContractSignature;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.application.application_info'))
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('ref_no')
                            ->label(__('admin.application.ref_no')),
                        TextEntry::make('status')
                            ->label(__('admin.application.status'))
                            ->badge()
                            ->color(fn (Application $record) => $record->status->getColor())
                            ->formatStateUsing(fn (Application $record) => $record->status->getLabel()),
                        TextEntry::make('created_at')
                            ->label(__('admin.lead.created_at'))
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('branch.name')
                            ->label(__('admin.branch.label'))
                            ->placeholder('-'),
                        TextEntry::make('season.name')
                            ->label(__('admin.season.label'))
                            ->placeholder('-'),
                        TextEntry::make('program.name')
                            ->label(__('admin.program.name'))
                            ->placeholder('-'),
                        TextEntry::make('rejection_reason')
                            ->label(__('admin.application.rejection_reason'))
                            ->placeholder('-')
                            ->columnSpanFull()
                            ->visible(fn (Application $record) => filled($record->rejection_reason)),
                    ]),

                Section::make(__('admin.student.student_information'))
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('applicationStudent.name')
                            ->label(__('admin.student.name'))
                            ->placeholder('-'),
                        TextEntry::make('applicationStudent.gender')
                            ->label(__('admin.student.gender'))
                            ->badge()
                            ->placeholder('-'),
                        TextEntry::make('applicationStudent.birth_date')
                            ->label(__('admin.student.birth_date'))
                            ->date()
                            ->placeholder('-'),
                        TextEntry::make('applicationStudent.civil_number')
                            ->label(__('admin.student.civil_number'))
                            ->placeholder('-'),
                        TextEntry::make('applicationStudent.state')
                            ->label(__('admin.student.state'))
                            ->placeholder('-'),
                        TextEntry::make('applicationStudent.governorate')
                            ->label(__('admin.student.governorate'))
                            ->placeholder('-'),
                        TextEntry::make('applicationStudent.village')
                            ->label(__('admin.student.village'))
                            ->placeholder('-'),
                        TextEntry::make('applicationStudent.house_number')
                            ->label(__('admin.student.house_number'))
                            ->placeholder('-'),
                        TextEntry::make('applicationStudent.parents_social_status')
                            ->label(__('admin.student.parents_social_status'))
                            ->placeholder('-'),
                    ]),

                Section::make(__('admin.application.contacts_section'))
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('contacts')
                            ->schema([
                                TextEntry::make('type')
                                    ->label(__('admin.application_contacts.type_label'))
                                    ->badge(),
                                TextEntry::make('name')
                                    ->label(__('admin.application_contacts.name'))
                                    ->placeholder('-'),
                                TextEntry::make('phone')
                                    ->label(__('admin.application_contacts.phone'))
                                    ->placeholder('-'),
                                TextEntry::make('email')
                                    ->label(__('admin.application_contacts.email'))
                                    ->placeholder('-'),
                                TextEntry::make('id_number')
                                    ->label(__('admin.application_contacts.id_number'))
                                    ->placeholder('-'),
                                TextEntry::make('relationship')
                                    ->label(__('admin.application_contacts.relationship'))
                                    ->placeholder('-'),
                                TextEntry::make('occupation')
                                    ->label(__('admin.application_contacts.occupation'))
                                    ->placeholder('-'),
                                TextEntry::make('work_address')
                                    ->label(__('admin.application_contacts.work_address'))
                                    ->placeholder('-'),
                                TextEntry::make('work_phone')
                                    ->label(__('admin.application_contacts.work_phone'))
                                    ->placeholder('-'),
                                IconEntry::make('is_guardian')
                                    ->label(__('admin.application_contacts.is_guardian'))
                                    ->boolean(),
                            ])
                            ->columns(3),
                    ]),

                Section::make(__('admin.application.contract'))
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('contract_link')
                            ->label(__('admin.application.contract_link'))
                            ->getStateUsing(fn (Application $record) => $record->contract?->token
                                ? route('contract.show', $record->contract->token)
                                : null)
                            ->copyable()
                            ->placeholder('-')
                            ->visible(fn (Application $record) => filled($record->contract?->token)),
                        TextEntry::make('contract.token_expires_at')
                            ->label(__('admin.application.contract_link_expires_at'))
                            ->dateTime()
                            ->placeholder('-')
                            ->visible(fn (Application $record) => filled($record->contract?->token)),
                        TextEntry::make('contract.signed_at')
                            ->label(__('admin.application.contract_signed_at'))
                            ->dateTime()
                            ->placeholder('-')
                            ->visible(fn (Application $record) => filled($record->contract?->signed_at)),
                        TextEntry::make('contract.signed_by_applicant')
                            ->label(__('admin.application.contract_signed_by'))
                            ->formatStateUsing(fn ($state) => $state
                                ? __('admin.application.contract_signed_online')
                                : __('admin.application.contract_signed_by_staff'))
                            ->placeholder('-')
                            ->visible(fn (Application $record) => $record->contract?->signed_by_applicant !== null),
                        TextEntry::make('contract.signature_path')
                            ->label(__('admin.application.contract_signature'))
                            ->formatStateUsing(fn () => __('admin.application.download'))
                            ->url(fn (Application $record) => $record->contract?->signature_path
                                ? asset('storage/'.$record->contract->signature_path)
                                : null)
                            ->openUrlInNewTab()
                            ->visible(fn (Application $record) => filled($record->contract?->signature_path)),
                        TextEntry::make('contract.file_path')
                            ->label(__('admin.application.contract_file'))
                            ->formatStateUsing(fn () => __('admin.application.download'))
                            ->url(fn (Application $record) => $record->contract?->file_path
                                ? asset('storage/'.$record->contract->file_path)
                                : null)
                            ->openUrlInNewTab()
                            ->visible(fn (Application $record) => filled($record->contract?->file_path)),
                    ])
                    ->visible(fn (Application $record) => in_array($record->status::class, [
                        WaitingContractSignature::class,
                        UnderReview::class,
                        Accepted::class,
                        Rejected::class,
                    ])),
            ]);
    }
}
